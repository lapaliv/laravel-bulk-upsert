<?php

namespace Lapaliv\BulkUpsert\Tests;

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager;
use Lapaliv\BulkUpsert\Providers\BulkUpsertServiceProvider;
use Lapaliv\BulkUpsert\Tests\Models\MysqlArticle;
use Lapaliv\BulkUpsert\Tests\Models\MysqlUser;
use Lapaliv\BulkUpsert\Tests\Models\PostgresArticle;
use Lapaliv\BulkUpsert\Tests\Models\PostgresUser;
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

        MysqlUser::createTable();
        PostgresUser::createTable();
        MysqlArticle::createTable();
        PostgresArticle::createTable();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

//        MysqlUser::dropTable();
//        PostgresUser::dropTable();
//        MysqlArticle::dropTable();
//        PostgresArticle::dropTable();
    }

    public function setUp(): void
    {
        parent::setUp();

        self::$manager->setAsGlobal();
        self::$manager->bootEloquent();

        $this->app->bind('db', fn() => self::$manager->getDatabaseManager());
        $this->app->register(BulkUpsertServiceProvider::class);
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
            'host' => '127.0.0.1',
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
            'url' => env('POSTGRES_URL'),
            'host' => '127.0.0.1',
            'port' => env('POSTGRES_PORT'),
            'database' => env('POSTGRES_DATABASE'),
            'username' => env('POSTGRES_USERNAME'),
            'password' => env('POSTGRES_PASSWORD'),
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
}
