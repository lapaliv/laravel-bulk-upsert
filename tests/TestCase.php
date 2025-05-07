<?php

namespace Lapaliv\BulkUpsert\Tests;

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager;
use Lapaliv\BulkUpsert\Providers\BulkUpsertServiceProvider;
use PDO;
use RuntimeException;

/**
 * @internal
 */
abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    private static Manager $manager;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (isset(self::$manager)) {
            return;
        }

        $dotenv = Dotenv::createMutable(dirname(__DIR__));
        $dotenv->load();

        self::configureManager();
    }

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        self::$manager->setAsGlobal();
        self::$manager->bootEloquent();

        $this->app->bind('db', fn() => self::$manager->getDatabaseManager());
        $this->app->register(BulkUpsertServiceProvider::class);
    }

    private static function configureManager(): void
    {
        $manager = new Manager();

        $manager->addConnection([
            'driver' => 'mysql',
            'url' => env('MYSQL_URL'),
            'host' => env('MYSQL_HOST', '127.0.0.1'),
            'port' => env('MYSQL_PORT'),
            'database' => env('MYSQL_DATABASE'),
            'username' => env('MYSQL_USERNAME'),
            'password' => env('MYSQL_PASSWORD'),
            'unix_socket' => env('MYSQL_SOCKET'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ], 'mysql');

        $manager->addConnection([
            'driver' => 'pgsql',
            'url' => env('POSTGRESQL_URL'),
            'host' => env('POSTGRESQL_HOST', '127.0.0.1'),
            'port' => env('POSTGRESQL_PORT'),
            'database' => env('POSTGRESQL_DATABASE'),
            'username' => env('POSTGRESQL_USERNAME'),
            'password' => env('POSTGRESQL_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ], 'pgsql');

        $manager->addConnection([
            'driver' => 'sqlite',
            'database' => self::getSqLitePath(),
            'prefix' => '',
        ], 'sqlite');

        $connection = env('DB_CONNECTION', 'mysql');

        self::$manager = $manager;

        self::$manager->setAsGlobal();
        self::$manager->bootEloquent();
        self::$manager->getDatabaseManager()->setDefaultConnection($connection);

        if ($connection === 'sqlite') {
            $sqlitePath = self::getSqLitePath();

            if (file_exists($sqlitePath)) {
                unlink($sqlitePath);
            }

            if (!is_dir(dirname($sqlitePath))) {
                mkdir(dirname($sqlitePath), 0777, true);
            }

            if (!touch($sqlitePath)) {
                throw new RuntimeException('SQLite database was not created');
            }
        }
    }

    private static function getSqLitePath(): string
    {
        return __DIR__ . '/database/database.sqlite';
    }
}
