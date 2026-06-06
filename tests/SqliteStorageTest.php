<?php
namespace JarirAhmed\ServerStats\Tests;

use JarirAhmed\ServerStats\Storage;
use JarirAhmed\ServerStats\StorageInterface;

/**
 * Exercises the PDO Storage against its SQLite fallback (no MySQL needed in CI).
 */
class SqliteStorageTest extends StorageContractTestCase
{
    private array $dbFiles = [];

    protected function makeStorage(): StorageInterface
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ss_test_' . uniqid('', true) . '.db';
        $this->dbFiles[] = $path;
        return new Storage([
            // Unused port forces the MySQL connection to fail -> SQLite fallback.
            'port'        => '59999',
            'sqlite_path' => $path,
            'logger'      => static fn(string $m) => null,
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->dbFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        $this->dbFiles = [];
    }
}
