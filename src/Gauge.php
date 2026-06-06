<?php
namespace JarirAhmed\ServerStats;

/**
 * Point-in-time value (e.g. queue depth, active connections). Each set() records a sample;
 * the latest sample is the current value.
 */
class Gauge
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

    public function set(float $value): self
    {
        $this->storage->save($this->name, $value, $this->labels);
        return $this;
    }

    /** Latest recorded value, or null if never set. */
    public function getValue(): ?float
    {
        $rows = $this->storage->query(['name' => $this->name, 'limit' => 1]);
        return $rows === [] ? null : (float) $rows[0]['value'];
    }
}
