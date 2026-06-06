<?php
namespace JarirAhmed\ServerStats;

use PDO;

class Storage {
    private $pdo;
    private string $driver;

    /**
     * @param array $config Optional overrides. Falls back to SERVER_STATS_* env vars, then defaults.
     *   Keys: host, port, database, username, password, charset, sqlite_path
     */
    public function __construct(array $config = []) {
        $cfg = static fn(string $key, $default) =>
            $config[$key] ?? getenv('SERVER_STATS_' . strtoupper($key)) ?: $default;

        $host    = $cfg('host', '127.0.0.1');
        $port    = $cfg('port', '3306');
        $db      = $cfg('database', 'server_stats');
        $user    = $cfg('username', 'root');
        $pass    = $cfg('password', '');
        $charset = $cfg('charset', 'utf8mb4');
        // Default outside vendor/ so reinstalls don't wipe it.
        $sqlitePath = $config['sqlite_path']
            ?? getenv('SERVER_STATS_SQLITE_PATH')
            ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'server_stats.db';

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
            // Fallback to SQLite if MySQL is unavailable.
            $this->pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        $this->driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->createTables();
    }

    private function createTables(): void {
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
        }
    }

    public function save(string $name, float $value, array $labels = []): void {
        $stmt = $this->pdo->prepare("INSERT INTO metrics (name, value, labels) VALUES (?, ?, ?)");
        $stmt->execute([$name, $value, json_encode($labels)]);
    }

    public function getLatest(): array {
        $stmt = $this->pdo->query("SELECT * FROM metrics ORDER BY created_at DESC, id DESC LIMIT 50");
        return $stmt->fetchAll();
    }

    /** Latest stored value for a metric name, or null if none. Used to make counters durable. */
    public function getCurrentValue(string $name): ?float {
        $stmt = $this->pdo->prepare(
            "SELECT value FROM metrics WHERE name = ? ORDER BY created_at DESC, id DESC LIMIT 1"
        );
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        return $row === false ? null : (float) $row['value'];
    }
}
