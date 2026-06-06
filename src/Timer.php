<?php
namespace JarirAhmed\ServerStats;

class Timer
{
    private string $name;
    private StorageInterface $storage;
    /** @var array<string,mixed> */
    private array $labels;
    private ?float $startTime = null;

    /** @param array<string,mixed> $labels */
    public function __construct(string $name, StorageInterface $storage, array $labels = [])
    {
        $this->name = $name;
        $this->storage = $storage;
        $this->labels = $labels;
    }

    public function start(): self
    {
        $this->startTime = microtime(true);
        return $this;
    }

    public function stop(): void
    {
        if ($this->startTime === null) {
            throw new \Exception("Timer not started");
        }
        $elapsed = (microtime(true) - $this->startTime) * 1000; // ms
        $this->startTime = null; // prevent double-save via __destruct
        $this->storage->save($this->name . '_ms', $elapsed, $this->labels);
    }

    public function __destruct()
    {
        if ($this->startTime !== null) {
            $this->stop();
        }
    }
}
