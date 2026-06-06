# Changelog

All notable changes to this project are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-06-06

### Added
- `StorageInterface` backend contract; `Storage` (PDO) and `InMemoryStorage` implementations.
- `Gauge` metric type for point-in-time values.
- `Registry` instance API alongside the static `Metrics` facade (avoids global state).
- Atomic counters stored in a dedicated `counters` table (race-safe under concurrency).
- Labels support on counters, gauges and timers (order-independent identity for counters).
- Query/aggregation API: `query()`, `getCounters()`, `aggregate()` (avg/min/max/sum/count).
- Retention: `prune()` plus optional probabilistic auto-prune (`retention_seconds`, `gc_probability`).
- Configurable logger; the MySQL→SQLite fallback now logs the reason instead of failing silently.
- PHPUnit test suite (InMemory + SQLite + optional MySQL) and GitHub Actions CI.
- README rewrite and this changelog.

### Changed
- Counters are now atomic and durable via the storage backend instead of in-process state.
- `getLatest()`/`query()` decode `labels` JSON into arrays.
- `MetricCollector` accepts a configurable disk path and is cross-platform safe.
- `value` column changed from `FLOAT` to `DOUBLE`; added indexes on `name`/`created_at`.
- Input validation: metric names are trimmed, rejected when empty, truncated to 255 chars.

### Fixed
- `Timer` no longer double-records when `__destruct` runs after `stop()`.
- `Metrics::measure()` stops its timer even when the callback throws (try/finally).
- `Metrics` throws a clear error when used before `init()`.

## [1.0.1] - 2026-06-06

### Fixed
- Replaced hardcoded DB credentials with constructor config + `SERVER_STATS_*` env vars.
- Moved the SQLite fallback database out of `vendor/` (defaults to the system temp dir).
- `Timer` reset after `stop()` to prevent duplicate saves via `__destruct`.
- Counter resumes from the last stored value.
- Driver detection via `PDO::ATTR_DRIVER_NAME` instead of exception probing.
- Added indexes and an uninitialized-storage guard; required `php >=8.0`, `ext-pdo`, `ext-json`.

## [1.0.0] - 2026-06-06

### Added
- Initial release: `Counter`, `Timer`, `Metrics`, `Storage`, `MetricCollector`.
