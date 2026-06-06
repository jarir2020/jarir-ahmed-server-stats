<?php
namespace JarirAhmed\ServerStats;

class Metrics
{
    private static ?Storage $storage = null;

    public static function init(Storage $storage): void
    {
        self::$storage = $storage;
    }

    private static function storage(): Storage
    {
        if (self::$storage === null) {
            throw new \RuntimeException(
                'Metrics not initialized. Call Metrics::init(new Storage()) before use.'
            );
        }
        return self::$storage;
    }

    public static function counter(string $name): Counter
    {
        return new Counter($name, self::storage());
    }

    public static function timer(string $name): Timer
    {
        return new Timer($name, self::storage());
    }

    public static function measure(string $name, callable $callback): mixed
    {
        $timer = self::timer($name);
        $timer->start();
        try {
            return call_user_func($callback);
        } finally {
            $timer->stop(); // runs even if callback throws
        }
    }
}
