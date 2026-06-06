<?php
namespace JarirAhmed\ServerStats\Tests;

use JarirAhmed\ServerStats\Counter;
use JarirAhmed\ServerStats\Gauge;
use JarirAhmed\ServerStats\InMemoryStorage;
use JarirAhmed\ServerStats\Metrics;
use JarirAhmed\ServerStats\Registry;
use JarirAhmed\ServerStats\Timer;
use PHPUnit\Framework\TestCase;

class MetricsTest extends TestCase
{
    protected function tearDown(): void
    {
        Metrics::setRegistry(null);
    }

    public function testThrowsWhenNotInitialized(): void
    {
        Metrics::setRegistry(null);
        $this->expectException(\RuntimeException::class);
        Metrics::counter('x');
    }

    public function testCounterIsDurableAcrossInstances(): void
    {
        Metrics::init(new InMemoryStorage());
        Metrics::counter('hits')->increment();
        Metrics::counter('hits')->increment();
        $this->assertSame(2.0, Metrics::counter('hits')->getValue());
    }

    public function testGaugeRecordsLatest(): void
    {
        Metrics::init(new InMemoryStorage());
        $g = Metrics::gauge('queue');
        $this->assertInstanceOf(Gauge::class, $g);
        $g->set(5);
        $g->set(9);
        $this->assertSame(9.0, Metrics::gauge('queue')->getValue());
    }

    public function testMeasureStopsTimerEvenWhenCallbackThrows(): void
    {
        $storage = new InMemoryStorage();
        Metrics::init($storage);
        try {
            Metrics::measure('boom', function () {
                throw new \RuntimeException('x');
            });
            $this->fail('exception not propagated');
        } catch (\RuntimeException $e) {
            // expected
        }
        $rows = $storage->query(['name' => 'boom_ms']);
        $this->assertCount(1, $rows);
    }

    public function testMeasureReturnsCallbackValue(): void
    {
        Metrics::init(new InMemoryStorage());
        $result = Metrics::measure('work', fn() => 42);
        $this->assertSame(42, $result);
    }

    public function testFactoriesReturnExpectedTypes(): void
    {
        Metrics::init(new InMemoryStorage());
        $this->assertInstanceOf(Counter::class, Metrics::counter('c'));
        $this->assertInstanceOf(Timer::class, Metrics::timer('t'));
    }
}
