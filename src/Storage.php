<?php
namespace JarirAhmed\ServerStats;

use PDO;

/**
 * PDO-backed storage. Tries MySQL first, falls back to SQLite if unavailable.
 *
 * Two tables:
 *   - metrics:  append-only time series (timers, gauges, system metrics)
 *   - counters: aggregate state, updated atomically (safe under concurrency)
 */
class Storage implements StorageInterface
{
    private const AGG_FUNCTIONS = [
        'avg' => 'AVG', 'min' => 'MIN', 'max' => 'MAX', 'sum' => 'SUM', 'count' => 'COUNT',
    ];

    private PDO $pdo;
    private string $driver;
    /** @var callable */
    private $logger;
    private int $retentionSeconds;
    private float $gcProbability;

    /**
     * @param array $config Optional overrides. Falls back to SERVER_STATS_* env vars, then defaults.
     *   Keys: host, port, database, username, password, charset, sqlite_path,
     *         logger (callable(string)), retention_seconds (int, 0=off),
     *         gc_probability (float 0..1, chance of auto-prune on save)
     */
    public function __construct(array $config = [])
    {
        $cfg = static fn(string $key, $default) =>
            $config[$key] ?? getenv('SERVER_STATS_' . strtoupper($key)) ?: $default;

        $host    = $cfg('host', '127.0.0.1');
        $port    = $cfg('port', '3306');
        $db      = $cfg('database', 'server_stats');
        $user    = $cfg('username', 'root');
        $pass    = $cfg('password', '');
        $charset = $cfg('charset', 'utf8mb4');
        $sqlitePath = $config['sqlite_path']
            ?? getenv('SERVER_STATS_SQLITE_PATH')
            ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'server_stats.db';

        $this->logger = $config['logger'] ?? static fn(string $m) => error_log('[server-stats] ' . $m);
        $this->retentionSeconds = (int) $cfg('retention_seconds', 0);
        $this->gcProbability = (float) ($config['gc_probability'] ?? 0.01);

        $dsn = "mysql:host=$host;port=$port;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `$db`");
            $this->pdo->exec("USE `$db`");
        } catch (\PDOException $e) {
            // MySQL unavailable: log why, then fall back to SQLite.
            ($this->logger)('MySQL unavailable, using SQLite fallback: ' . $e->getMessage());
            $this->pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        $this->driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->createTables();
    }

    private function createTables(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                value DOUBLE NOT NULL,
                labels JSON DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_name (name),
                INDEX idx_created_at (created_at),
                INDEX idx_name_created (name, created_at)
            ) ENGINE=InnoDB");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS counters (
                name VARCHAR(255) NOT NULL,
                labels_hash CHAR(40) NOT NULL,
                labels JSON DEFAULT NULL,
                value DOUBLE NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (name, labels_hash)
            ) ENGINE=InnoDB");
        } else {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS metrics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                value REAL NOT NULL,
                labels TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_name ON metrics (name)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_created_at ON metrics (created_at)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_name_created ON metrics (name, created_at)");
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS counters (
                name TEXT NOT NULL,
                labels_hash TEXT NOT NULL,
                labels TEXT,
                value REAL NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (name, labels_hash)
            )");
        }
    }

    public function save(string $name, float $value, array $labels = []): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO metrics (name, value, labels) VALUES (?, ?, ?)");
        $stmt->execute([$this->normalizeName($name), $value, $this->encodeLabels($labels)]);
        $this->maybeGc();
    }

    public function incrementCounter(string $name, float $amount = 1.0, array $labels = []): float
    {
        $name = $this->normalizeName($name);
        $hash = $this->labelsHash($labels);
        $json = $this->encodeLabels($labels);

        if ($this->driver === 'mysql') {
            $sql = "INSERT INTO counters (name, labels_hash, labels, value) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE value = value + VALUES(value)";
        } else {
            $sql = "INSERT INTO counters (name, labels_hash, labels, value) VALUES (?, ?, ?, ?)
                    ON CONFLICT(name, labels_hash) DO UPDATE SET value = value + excluded.value,
                    updated_at = CURRENT_TIMESTAMP";
        }
        $this->pdo->prepare($sql)->execute([$name, $hash, $json, $amount]);

        return $this->getCounter($name, $labels);
    }

    public function getCounter(string $name, array $labels = []): float
    {
        $stmt = $this->pdo->prepare("SELECT value FROM counters WHERE name = ? AND labels_hash = ?");
        $stmt->execute([$this->normalizeName($name), $this->labelsHash($labels)]);
        $row = $stmt->fetch();
        return $row === false ? 0.0 : (float) $row['value'];
    }

    public function getLatest(int $limit = 50): array
    {
        $limit = $this->clampLimit($limit);
        $stmt = $this->pdo->query("SELECT * FROM metrics ORDER BY created_at DESC, id DESC LIMIT $limit");
        return $this->decodeRows($stmt->fetchAll());
    }

    public function getCounters(int $limit = 50): array
    {
        $limit = $this->clampLimit($limit);
        $stmt = $this->pdo->query("SELECT * FROM counters ORDER BY updated_at DESC LIMIT $limit");
        return $this->decodeRows($stmt->fetchAll());
    }

    public function query(array $filters = []): array
    {
        $where = [];
        $params = [];
        if (isset($filters['name'])) {
            $where[] = 'name = ?';
            $params[] = $this->normalizeName((string) $filters['name']);
        }
        if (isset($filters['since'])) {
            $where[] = 'created_at >= ?';
            $params[] = $this->tsToString((int) $filters['since']);
        }
        if (isset($filters['until'])) {
            $where[] = 'created_at <= ?';
            $params[] = $this->tsToString((int) $filters['until']);
        }
        $limit = $this->clampLimit((int) ($filters['limit'] ?? 50));
        $sql = "SELECT * FROM metrics";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY created_at DESC, id DESC LIMIT $limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->decodeRows($stmt->fetchAll());
    }

    public function aggregate(string $name, string $fn, ?int $sinceTs = null): ?float
    {
        $fn = strtolower($fn);
        if (!isset(self::AGG_FUNCTIONS[$fn])) {
            throw new \InvalidArgumentException("Unsupported aggregate function: $fn");
        }
        $sqlFn = self::AGG_FUNCTIONS[$fn];
        $params = [$this->normalizeName($name)];
        $sql = "SELECT $sqlFn(value) AS result FROM metrics WHERE name = ?";
        if ($sinceTs !== null) {
            $sql .= ' AND created_at >= ?';
            $params[] = $this->tsToString($sinceTs);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return ($row === false || $row['result'] === null) ? null : (float) $row['result'];
    }

    public function prune(int $olderThanSeconds): int
    {
        if ($olderThanSeconds <= 0) {
            return 0;
        }
        $cutoff = $this->tsToString(time() - $olderThanSeconds);
        $stmt = $this->pdo->prepare("DELETE FROM metrics WHERE created_at < ?");
        $stmt->execute([$cutoff]);
        return $stmt->rowCount();
    }

    // --- helpers ------------------------------------------------------------

    /** Probabilistic retention sweep, à la PHP session gc, to avoid per-request cost. */
    private function maybeGc(): void
    {
        if ($this->retentionSeconds > 0 && $this->gcProbability > 0
            && (mt_rand() / mt_getrandmax()) < $this->gcProbability) {
            try {
                $this->prune($this->retentionSeconds);
            } catch (\PDOException $e) {
                ($this->logger)('Retention prune failed: ' . $e->getMessage());
            }
        }
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Metric name must not be empty.');
        }
        return mb_substr($name, 0, 255);
    }

    /** @param array<string,mixed> $labels */
    private function encodeLabels(array $labels): string
    {
        $json = json_encode($labels);
        return $json === false ? '[]' : $json;
    }

    /** Stable identity hash for a label set (order-independent). @param array<string,mixed> $labels */
    private function labelsHash(array $labels): string
    {
        ksort($labels);
        return sha1($this->encodeLabels($labels));
    }

    private function clampLimit(int $limit): int
    {
        return max(1, min($limit, 10000));
    }

    private function tsToString(int $ts): string
    {
        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function decodeRows(array $rows): array
    {
        foreach ($rows as &$row) {
            if (array_key_exists('labels', $row)) {
                $decoded = is_string($row['labels']) ? json_decode($row['labels'], true) : null;
                $row['labels'] = is_array($decoded) ? $decoded : [];
            }
        }
        return $rows;
    }
}
