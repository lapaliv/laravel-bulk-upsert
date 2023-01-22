<?php

namespace Lapaliv\BulkUpsert\Tests;

use Dotenv\Dotenv;
use Lapaliv\BulkUpsert\Contracts\DriverManager;
use Lapaliv\BulkUpsert\Providers\BulkUpsertServiceProvider;
use Lapaliv\BulkUpsert\Tests\App\Drivers\NullDriver;
use Orchestra\Testbench\TestCase;

abstract class UnitTestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $dotenv = Dotenv::createMutable(dirname(__DIR__));
        $dotenv->load();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(BulkUpsertServiceProvider::class);

        /** @var DriverManager $driverManager */
        $driverManager = $this->app->make(DriverManager::class);

        foreach ($driverManager->all() as $name => $driver) {
            $driverManager->registerDriver($name, new NullDriver());
        }
    }
}
