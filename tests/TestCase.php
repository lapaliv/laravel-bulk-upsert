<?php

namespace Tests;

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager;
use Lapaliv\BulkUpsert\Providers\BulkUpsertServiceProvider;
use PDO;
use RuntimeException;

/**
 * The base test case of the suite.
 *
 * It reads the environment file, builds the database manager with the mysql, pgsql and
 * sqlite connections and registers the service provider of the package for every test.
 *
 * @internal
 */
abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * The database manager which every test of the suite shares.
     *
     * @var \Illuminate\Database\Capsule\Manager
     */
    private static Manager $manager;

    /**
     * Set the database up before the first test of the class.
     *
     * The environment file is read and the manager is built only once for the whole
     * run. Every later call leaves the ready manager alone.
     *
     * @return void
     */
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

    /**
     * Build the database manager and all of its connections.
     *
     * The manager gets a mysql, a pgsql and a sqlite connection. The connection which
     * `DB_CONNECTION` names becomes the default one.
     *
     * For sqlite the method also removes the old database file and creates an empty
     * one, so every run starts with a clean file.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
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

    /**
     * Get the path of the sqlite database file of the tests.
     *
     * @return string
     */
    private static function getSqLitePath(): string
    {
        return __DIR__ . '/Database/database.sqlite';
    }
}
