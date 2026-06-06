<?php
namespace JarirAhmed\ServerStats\Tests;

use JarirAhmed\ServerStats\InMemoryStorage;
use JarirAhmed\ServerStats\Registry;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    public function testTwoRegistriesAreIsolated(): void
    {
        $a = new Registry(new InMemoryStorage());
        $b = new Registry(new InMemoryStorage());

        $a->counter('hits')->increment();
        $a->counter('hits')->increment();
        $b->counter('hits')->increment();

        $this->assertSame(2.0, $a->counter('hits')->getValue());
        $this->assertSame(1.0, $b->counter('hits')->getValue());
    }

    public function testGaugeCounterTimerShareBackend(): void
    {
        $storage = new InMemoryStorage();
        $r = new Registry($storage);
        $r->gauge('temp')->set(21.5);
        $this->assertSame(21.5, $r->gauge('temp')->getValue());

        $r->measure('job', fn() => null);
        $this->assertCount(1, $storage->query(['name' => 'job_ms']));
    }
}
