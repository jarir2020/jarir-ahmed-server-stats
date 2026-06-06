<?php
namespace JarirAhmed\ServerStats;

class Counter
{
    private $name;
    private $storage;
    private $value = 0;

    public function __construct(string $name, Storage $storage)
    {
        $this->name = $name;
        $this->storage = $storage;
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
