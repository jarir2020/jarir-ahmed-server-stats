<?php
namespace JarirAhmed\ServerStats;

/**
 * In-memory storage backend. No persistence — primarily for tests and ephemeral use.
 */
class InMemoryStorage implements StorageInterface
{
    private const AGG_FUNCTIONS = ['avg', 'min', 'max', 'sum', 'count'];

    /** @var array<int,array<string,mixed>> */
    private array $metrics = [];
    /** @var array<string,array<string,mixed>> keyed by "name\0hash" */
    private array $counters = [];
    private int $seq = 0;

    public function save(string $name, float $value, array $labels = []): void
    {
        $this->metrics[] = [
            'id'         => ++$this->seq,
            'name'       => $this->normalizeName($name),
            'value'      => $value,
            'labels'     => $labels,
            'created_at' => $this->now(),
            '_ts'        => time(),
        ];
    }

    public function incrementCounter(string $name, float $amount = 1.0, array $labels = []): float
    {
        $name = $this->normalizeName($name);
        $key = $name . "\0" . $this->labelsHash($labels);
        if (!isset($this->counters[$key])) {
            $this->counters[$key] = [
                'name' => $name, 'labels' => $labels, 'value' => 0.0, 'updated_at' => $this->now(),
            ];
        }
        $this->counters[$key]['value'] += $amount;
        $this->counters[$key]['updated_at'] = $this->now();
        return (float) $this->counters[$key]['value'];
    }

    public function getCounter(string $name, array $labels = []): float
    {
        $key = $this->normalizeName($name) . "\0" . $this->labelsHash($labels);
        return isset($this->counters[$key]) ? (float) $this->counters[$key]['value'] : 0.0;
    }

    public function getLatest(int $limit = 50): array
    {
        $rows = array_reverse($this->metrics);
        return $this->strip(array_slice($rows, 0, max(1, $limit)));
    }

    public function getCounters(int $limit = 50): array
    {
        $rows = array_values($this->counters);
        usort($rows, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
        return array_slice($rows, 0, max(1, $limit));
    }

    public function query(array $filters = []): array
    {
        $rows = array_reverse($this->metrics);
        if (isset($filters['name'])) {
            $name = $this->normalizeName((string) $filters['name']);
            $rows = array_filter($rows, fn($r) => $r['name'] === $name);
        }
        if (isset($filters['since'])) {
            $rows = array_filter($rows, fn($r) => $r['_ts'] >= (int) $filters['since']);
        }
        if (isset($filters['until'])) {
            $rows = array_filter($rows, fn($r) => $r['_ts'] <= (int) $filters['until']);
        }
        return $this->strip(array_slice(array_values($rows), 0, max(1, (int) ($filters['limit'] ?? 50))));
    }

    public function aggregate(string $name, string $fn, ?int $sinceTs = null): ?float
    {
        $fn = strtolower($fn);
        if (!in_array($fn, self::AGG_FUNCTIONS, true)) {
            throw new \InvalidArgumentException("Unsupported aggregate function: $fn");
        }
        $name = $this->normalizeName($name);
        $values = [];
        foreach ($this->metrics as $r) {
            if ($r['name'] === $name && ($sinceTs === null || $r['_ts'] >= $sinceTs)) {
                $values[] = $r['value'];
            }
        }
        if ($fn === 'count') {
            return (float) count($values);
        }
        if (!$values) {
            return null;
        }
        return match ($fn) {
            'avg' => array_sum($values) / count($values),
            'min' => (float) min($values),
            'max' => (float) max($values),
            'sum' => (float) array_sum($values),
        };
    }

    public function prune(int $olderThanSeconds): int
    {
        if ($olderThanSeconds <= 0) {
            return 0;
        }
        $cutoff = time() - $olderThanSeconds;
        $before = count($this->metrics);
        $this->metrics = array_values(array_filter($this->metrics, fn($r) => $r['_ts'] >= $cutoff));
        return $before - count($this->metrics);
    }

    // --- helpers ------------------------------------------------------------

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Metric name must not be empty.');
        }
        return mb_substr($name, 0, 255);
    }

    /** @param array<string,mixed> $labels */
    private function labelsHash(array $labels): string
    {
        ksort($labels);
        $json = json_encode($labels);
        return sha1($json === false ? '[]' : $json);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** Drop the internal _ts column from returned rows. */
    private function strip(array $rows): array
    {
        return array_map(static function ($r) {
            unset($r['_ts']);
            return $r;
        }, $rows);
    }
}
