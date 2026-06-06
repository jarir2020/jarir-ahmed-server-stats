# server-stats

[![CI](https://github.com/jarir2020/jarir-ahmed-server-stats/actions/workflows/ci.yml/badge.svg)](https://github.com/jarir2020/jarir-ahmed-server-stats/actions/workflows/ci.yml)

A small PHP metrics library: **counters**, **gauges**, **timers**, and **system metrics**, stored in
**MySQL** (with automatic **SQLite** fallback). No external services required.

## Requirements

- PHP >= 8.0
- `ext-pdo`, `ext-json`
- `ext-pdo_mysql` (optional — falls back to SQLite if MySQL is unavailable)

## Install

```bash
composer require jarir-ahmed/server-stats
```

## Quick start

```php
use JarirAhmed\ServerStats\{Storage, Metrics, MetricCollector};

$storage = new Storage(); // MySQL if reachable, else SQLite in the system temp dir
Metrics::init($storage);

// Counter — atomic, durable across requests/processes
Metrics::counter('page_views.home')->increment();

// Gauge — point-in-time value (latest wins)
Metrics::gauge('queue.depth')->set(42);

// Timer — measure a block; stops even if the callback throws
$result = Metrics::measure('request.processing', function () {
    return doWork();
});

// System metrics (CPU load, memory, disk)
(new MetricCollector($storage))->recordSystemMetrics();
```

## Configuration

`Storage` reads, in order: the `$config` array → `SERVER_STATS_*` environment variables → defaults.

```php
$storage = new Storage([
    'host'             => '127.0.0.1',
    'port'             => '3306',
    'database'         => 'server_stats',
    'username'         => 'root',
    'password'         => '',
    'charset'          => 'utf8mb4',
    'sqlite_path'      => '/var/data/server_stats.db', // SQLite fallback location
    'retention_seconds'=> 7 * 86400,  // auto-prune samples older than this (0 = off)
    'gc_probability'   => 0.01,        // chance per save() of running a prune sweep
    'logger'           => fn(string $m) => error_log($m),
]);
```

Equivalent env vars: `SERVER_STATS_HOST`, `SERVER_STATS_PORT`, `SERVER_STATS_DATABASE`,
`SERVER_STATS_USERNAME`, `SERVER_STATS_PASSWORD`, `SERVER_STATS_CHARSET`,
`SERVER_STATS_SQLITE_PATH`, `SERVER_STATS_RETENTION_SECONDS`.

## Labels

Counters, gauges and timers accept labels. For counters, labels are part of the identity
(order-independent), so each label set is tracked separately.

```php
Metrics::counter('http.requests', ['status' => 200])->increment();
Metrics::counter('http.requests', ['status' => 500])->increment();

Metrics::counter('http.requests', ['status' => 200])->getValue(); // 1.0
```

## Querying

```php
$storage->getLatest(20);                       // recent time-series samples (newest first)
$storage->getCounters();                       // all counters
$storage->query(['name' => 'request.processing_ms', 'since' => time() - 3600]);
$storage->aggregate('request.processing_ms', 'avg', time() - 3600); // avg|min|max|sum|count
$storage->prune(7 * 86400);                    // delete samples older than 7 days
```

## Without global state

Use `Registry` directly instead of the static `Metrics` facade — handy for multiple backends
or test isolation:

```php
use JarirAhmed\ServerStats\{Registry, InMemoryStorage};

$registry = new Registry(new InMemoryStorage());
$registry->counter('hits')->increment();
$registry->gauge('temp')->set(21.5);
```

`InMemoryStorage` implements the same `StorageInterface` and persists nothing — ideal for tests.

## Architecture

- `StorageInterface` — backend contract.
- `Storage` — PDO backend (MySQL + SQLite). Two tables: `metrics` (time series) and
  `counters` (atomic aggregate state).
- `InMemoryStorage` — non-persistent backend.
- `Registry` — instance API; `Metrics` — static facade over a default registry.
- `Counter`, `Gauge`, `Timer`, `MetricCollector`.

## Testing

```bash
composer install
composer test
```

The suite runs against `InMemoryStorage` and the SQLite backend. Set `SERVER_STATS_TEST_MYSQL=1`
(with MySQL connection env vars) to additionally run the MySQL contract tests.

## License

MIT
