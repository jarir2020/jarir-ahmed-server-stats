<?php
namespace JarirAhmed\ServerStats;

class Metrics
{
    private static $storage;

    public static function init(Storage $storage): void
    {
        self::$storage = $storage;
    }

    public static function counter(string $name): Counter
    {
        return new Counter($name, self::$storage);
    }

    public static function timer(string $name): Timer
    {
        return new Timer($name, self::$storage);
    }

    public static function measure(string $name, callable $callback): mixed
    {
        $timer = self::timer($name);
        $timer->start();
        $result = call_user_func($callback);
        $timer->stop();
        return $result;
    }
}
