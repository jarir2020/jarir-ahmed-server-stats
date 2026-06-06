<?php
namespace JarirAhmed\ServerStats;

/**
 * Backend contract for metric storage. Implementations: Storage (PDO/MySQL+SQLite),
 * InMemoryStorage (tests / no DB).
 */
interface StorageInterface
{
    /**
     * Append a time-series sample (timers, gauges, system metrics).
     *
     * @param array<string,mixed> $labels
     */
    public function save(string $name, float $value, array $labels = []): void;

    /**
     * Atomically add to a named counter and return the new value.
     * Counters are aggregate state, not time series, so this is safe under concurrency.
     *
     * @param array<string,mixed> $labels Part of the counter identity.
     */
    public function incrementCounter(string $name, float $amount = 1.0, array $labels = []): float;

    /**
     * Current value of a counter, or 0.0 if it does not exist.
     *
     * @param array<string,mixed> $labels
     */
    public function getCounter(string $name, array $labels = []): float;

    /**
     * Most recent time-series samples, newest first. Labels are decoded to arrays.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getLatest(int $limit = 50): array;

    /**
     * All counters, newest update first. Labels decoded.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getCounters(int $limit = 50): array;

    /**
     * Filtered time-series query.
     *
     * @param array{name?:string, since?:int, until?:int, limit?:int} $filters
     *        since/until are unix timestamps.
     * @return array<int,array<string,mixed>>
     */
    public function query(array $filters = []): array;

    /**
     * Aggregate a metric's samples.
     *
     * @param string $fn One of: avg, min, max, sum, count.
     * @param int|null $sinceTs Only include samples at/after this unix timestamp.
     */
    public function aggregate(string $name, string $fn, ?int $sinceTs = null): ?float;

    /**
     * Delete time-series samples older than the given age in seconds.
     *
     * @return int Rows removed.
     */
    public function prune(int $olderThanSeconds): int;
}
