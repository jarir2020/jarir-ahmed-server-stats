<?php
namespace JarirAhmed\ServerStats;

class Counter
{
    private $name;
    private $storage;
    private $value;

    public function __construct(string $name, Storage $storage)
    {
        $this->name = $name;
        $this->storage = $storage;
        // Resume from last stored value so counters survive across requests/processes.
        $this->value = $storage->getCurrentValue($name) ?? 0.0;
    }

    public function increment(float $amount = 1): self
    {
        $this->value += $amount;
        $this->storage->save($this->name, $this->value);
        return $this;
    }

    public function getValue(): float
    {
        return $this->value;
    }
}
