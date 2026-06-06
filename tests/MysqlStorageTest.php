<?php
namespace JarirAhmed\ServerStats\Tests;

use JarirAhmed\ServerStats\Storage;
use JarirAhmed\ServerStats\StorageInterface;

/**
 * Runs the storage contract against a real MySQL backend.
 * Skipped unless SERVER_STATS_TEST_MYSQL=1 (set in CI, where a MySQL service is available).
 */
class MysqlStorageTest extends StorageContractTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (getenv('SERVER_STATS_TEST_MYSQL') !== '1') {
            self::markTestSkipped('MySQL tests disabled (set SERVER_STATS_TEST_MYSQL=1 to enable).');
        }
        if (!extension_loaded('pdo_mysql')) {
            self::markTestSkipped('pdo_mysql extension not loaded.');
        }
    }

    protected function makeStorage(): StorageInterface
    {
        $storage = new Storage([
            'host'     => getenv('SERVER_STATS_HOST') ?: '127.0.0.1',
            'port'     => getenv('SERVER_STATS_PORT') ?: '3306',
            'username' => getenv('SERVER_STATS_USERNAME') ?: 'root',
            'password' => getenv('SERVER_STATS_PASSWORD') ?: '',
            'database' => getenv('SERVER_STATS_DATABASE') ?: 'server_stats_test',
            'logger'   => static fn(string $m) => null,
        ]);
        // Fresh state per test.
        $pdo = (new \ReflectionObject($storage))->getProperty('pdo');
        $pdo->setAccessible(true);
        /** @var \PDO $conn */
        $conn = $pdo->getValue($storage);
        if ($conn->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            self::markTestSkipped('MySQL connection failed; got SQLite fallback.');
        }
        $conn->exec('TRUNCATE TABLE metrics');
        $conn->exec('TRUNCATE TABLE counters');
        return $storage;
    }
}
