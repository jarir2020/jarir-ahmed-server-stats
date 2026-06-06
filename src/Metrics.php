<?php
namespace JarirAhmed\ServerStats;

/**
 * Static facade over a default {@see Registry}. Convenient for simple apps.
 * For multiple backends or test isolation, use Registry directly.
 */
class Metrics
{
    private static ?Registry $registry = null;

    public static function init(StorageInterface $storage): void
    {
        self::$registry = new Registry($storage);
    }

    /** Replace/clear the default registry (mainly for tests). */
    public static function setRegistry(?Registry $registry): void
    {
        self::$registry = $registry;
    }

    private static function registry(): Registry
    {
        if (self::$registry === null) {
            throw new \RuntimeException(
                'Metrics not initialized. Call Metrics::init(new Storage()) before use.'
            );
        }
        return self::$registry;
    }

    /** @param array<string,mixed> $labels */
    public static function counter(string $name, array $labels = []): Counter
    {
        return self::registry()->counter($name, $labels);
    }

    /** @param array<string,mixed> $labels */
    public static function timer(string $name, array $labels = []): Timer
    {
        return self::registry()->timer($name, $labels);
    }

    /** @param array<string,mixed> $labels */
    public static function gauge(string $name, array $labels = []): Gauge
    {
        return self::registry()->gauge($name, $labels);
    }

    /** @param array<string,mixed> $labels */
    public static function measure(string $name, callable $callback, array $labels = []): mixed
    {
        return self::registry()->measure($name, $callback, $labels);
    }
}
