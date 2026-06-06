<?php
namespace JarirAhmed\ServerStats;

class Counter
{
    private string $name;
    private StorageInterface $storage;
    /** @var array<string,mixed> */
    private array $labels;

    /** @param array<string,mixed> $labels */
    public function __construct(string $name, StorageInterface $storage, array $labels = [])
    {
        $this->name = $name;
        $this->storage = $storage;
        $this->labels = $labels;
    }

    /** Atomically increment; returns $this for chaining. */
    public function increment(float $amount = 1): self
    {
        $this->storage->incrementCounter($this->name, $amount, $this->labels);
        return $this;
    }

    public function getValue(): float
    {
        return $this->storage->getCounter($this->name, $this->labels);
    }
}
