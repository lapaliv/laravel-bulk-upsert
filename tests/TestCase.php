<?php

namespace Lapaliv\BulkUpsert\Tests;

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager;
use Lapaliv\BulkUpsert\Providers\BulkUpsertServiceProvider;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithAutoIncrement;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithoutAutoIncrement;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlArticle;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use PDO;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    private static Manager $manager;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (isset(self::$manager)) {
            return;
        }

        self::readEnv();
        self::configureManager();

        MySqlEntityWithAutoIncrement::createTable();
        MySqlEntityWithoutAutoIncrement::createTable();

        MySqlUser::dropTable();
        MySqlUser::createTable();
        PostgreSqlUser::dropTable();
        PostgreSqlUser::createTable();
        MySqlArticle::dropTable();
        MySqlArticle::createTable();
        PostgreSqlArticle::dropTable();
        PostgreSqlArticle::createTable();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
    }

    private static function readEnv(): void
    {
        $dotenv = Dotenv::createMutable(dirname(__DIR__));
        $dotenv->load();
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
        ], 'postgres');

        self::$manager = $manager;

        self::$manager->setAsGlobal();
        self::$manager->bootEloquent();
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::$manager->setAsGlobal();
        self::$manager->bootEloquent();

        $this->app->bind('db', fn () => self::$manager->getDatabaseManager());
        $this->app->register(BulkUpsertServiceProvider::class);
    }
}
