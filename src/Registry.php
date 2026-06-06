<?php
namespace JarirAhmed\ServerStats;

/**
 * Instance-based metrics registry. Use this when you need more than one storage backend
 * or want to avoid global state (e.g. in tests). The static {@see Metrics} facade wraps one.
 */
class Registry
{
    private StorageInterface $storage;

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    public function storage(): StorageInterface
    {
        return $this->storage;
    }

    /** @param array<string,mixed> $labels */
    public function counter(string $name, array $labels = []): Counter
    {
        return new Counter($name, $this->storage, $labels);
    }

    /** @param array<string,mixed> $labels */
    public function timer(string $name, array $labels = []): Timer
    {
        return new Timer($name, $this->storage, $labels);
    }

    /** @param array<string,mixed> $labels */
    public function gauge(string $name, array $labels = []): Gauge
    {
        return new Gauge($name, $this->storage, $labels);
    }

    /**
     * Time a callback and record its duration. The timer stops even if the callback throws.
     *
     * @param array<string,mixed> $labels
     * @return mixed The callback's return value.
     */
    public function measure(string $name, callable $callback, array $labels = []): mixed
    {
        $timer = $this->timer($name, $labels)->start();
        try {
            return $callback();
        } finally {
            $timer->stop();
        }
    }
}
