<?php
namespace JarirAhmed\ServerStats\Tests;

use JarirAhmed\ServerStats\StorageInterface;
use PHPUnit\Framework\TestCase;

/**
 * Shared behavioural contract that every StorageInterface implementation must satisfy.
 */
abstract class StorageContractTestCase extends TestCase
{
    abstract protected function makeStorage(): StorageInterface;

    public function testSaveAndGetLatestReturnsNewestFirst(): void
    {
        $s = $this->makeStorage();
        $s->save('cpu', 1.0);
        $s->save('cpu', 2.0);
        $rows = $s->getLatest();
        $this->assertSame('cpu', $rows[0]['name']);
        $this->assertSame(2.0, (float) $rows[0]['value']);
    }

    public function testLabelsAreDecodedToArray(): void
    {
        $s = $this->makeStorage();
        $s->save('http', 5.0, ['route' => '/home', 'method' => 'GET']);
        $rows = $s->getLatest();
        $this->assertIsArray($rows[0]['labels']);
        $this->assertSame('/home', $rows[0]['labels']['route']);
    }

    public function testCounterIncrementsAtomicallyAccumulate(): void
    {
        $s = $this->makeStorage();
        $this->assertSame(0.0, $s->getCounter('hits'));
        $this->assertSame(1.0, $s->incrementCounter('hits'));
        $this->assertSame(3.0, $s->incrementCounter('hits', 2));
        $this->assertSame(3.0, $s->getCounter('hits'));
    }

    public function testCountersSeparatedByLabels(): void
    {
        $s = $this->makeStorage();
        $s->incrementCounter('req', 1, ['code' => 200]);
        $s->incrementCounter('req', 1, ['code' => 200]);
        $s->incrementCounter('req', 1, ['code' => 500]);
        $this->assertSame(2.0, $s->getCounter('req', ['code' => 200]));
        $this->assertSame(1.0, $s->getCounter('req', ['code' => 500]));
    }

    public function testLabelHashIsOrderIndependent(): void
    {
        $s = $this->makeStorage();
        $s->incrementCounter('x', 1, ['a' => 1, 'b' => 2]);
        $this->assertSame(1.0, $s->getCounter('x', ['b' => 2, 'a' => 1]));
    }

    public function testQueryFiltersByName(): void
    {
        $s = $this->makeStorage();
        $s->save('a', 1.0);
        $s->save('b', 2.0);
        $rows = $s->query(['name' => 'a']);
        $this->assertCount(1, $rows);
        $this->assertSame('a', $rows[0]['name']);
    }

    public function testAggregateFunctions(): void
    {
        $s = $this->makeStorage();
        foreach ([2.0, 4.0, 6.0] as $v) {
            $s->save('lat', $v);
        }
        $this->assertSame(4.0, $s->aggregate('lat', 'avg'));
        $this->assertSame(2.0, $s->aggregate('lat', 'min'));
        $this->assertSame(6.0, $s->aggregate('lat', 'max'));
        $this->assertSame(12.0, $s->aggregate('lat', 'sum'));
        $this->assertSame(3.0, $s->aggregate('lat', 'count'));
        $this->assertNull($s->aggregate('missing', 'avg'));
    }

    public function testAggregateRejectsUnknownFunction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->makeStorage()->aggregate('x', 'drop'); // not in whitelist
    }

    public function testPruneKeepsRecentSamples(): void
    {
        $s = $this->makeStorage();
        $s->save('a', 1.0);
        $this->assertSame(0, $s->prune(3600)); // nothing is an hour old yet
        $this->assertCount(1, $s->getLatest());
        $this->assertSame(0, $s->prune(0)); // no-op
    }

    public function testEmptyNameRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->makeStorage()->save('   ', 1.0);
    }

    public function testLongNameTruncatedTo255(): void
    {
        $s = $this->makeStorage();
        $long = str_repeat('x', 300);
        $s->save($long, 1.0);
        $rows = $s->getLatest();
        $this->assertSame(255, strlen($rows[0]['name']));
    }
}
