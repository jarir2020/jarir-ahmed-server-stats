<?php
namespace JarirAhmed\ServerStats;

class Timer
{
    private $name;
    private $storage;
    private $startTime;

    public function __construct(string $name, Storage $storage)
    {
        $this->name = $name;
        $this->storage = $storage;
    }

    public function start(): self
    {
        $this->startTime = microtime(true);
        return $this;
    }

    public function stop(): void
    {
        if (!$this->startTime) {
            throw new \Exception("Timer not started");
        }
        $elapsed = (microtime(true) - $this->startTime) * 1000; // ms
        $this->storage->save($this->name . '_ms', $elapsed);
    }

    public function __destruct()
    {
        if ($this->startTime) {
            $this->stop();
        }
    }
}
