<?php
namespace JarirAhmed\ServerStats\Tests;

use JarirAhmed\ServerStats\InMemoryStorage;
use JarirAhmed\ServerStats\StorageInterface;

class InMemoryStorageTest extends StorageContractTestCase
{
    protected function makeStorage(): StorageInterface
    {
        return new InMemoryStorage();
    }
}
