<?php
namespace JarirAhmed\ServerStats\Tests;

use JarirAhmed\ServerStats\InMemoryStorage;
use JarirAhmed\ServerStats\Timer;
use PHPUnit\Framework\TestCase;

class TimerTest extends TestCase
{
    public function testRecordsSingleSampleAndDestructDoesNotDuplicate(): void
    {
        $storage = new InMemoryStorage();
        $timer = new Timer('req', $storage);
        $timer->start();
        usleep(1000);
        $timer->stop();
        unset($timer); // __destruct must not save again
        gc_collect_cycles();

        $rows = $storage->query(['name' => 'req_ms']);
        $this->assertCount(1, $rows);
        $this->assertGreaterThan(0.0, $rows[0]['value']);
    }

    public function testDestructStopsRunningTimerExactlyOnce(): void
    {
        $storage = new InMemoryStorage();
        $timer = new Timer('auto', $storage);
        $timer->start();
        usleep(1000);
        unset($timer); // stop() fires via __destruct
        gc_collect_cycles();

        $this->assertCount(1, $storage->query(['name' => 'auto_ms']));
    }

    public function testStopWithoutStartThrows(): void
    {
        $this->expectException(\Exception::class);
        (new Timer('x', new InMemoryStorage()))->stop();
    }
}
