<?php

namespace Lapaliv\BulkUpsert\Tests;

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Providers\BulkUpsertServiceProvider;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlComment;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlPost;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlStory;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlComment;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlPost;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlStory;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Mockery\VerificationDirector;
use PDO;

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

        MySqlComment::dropTable();
        MySqlPost::dropTable();
        MySqlUser::dropTable();
        MySqlStory::dropTable();

        PostgreSqlComment::dropTable();
        PostgreSqlPost::dropTable();
        PostgreSqlUser::dropTable();
        PostgreSqlStory::dropTable();

        MySqlUser::createTable();
        MySqlPost::createTable();
        MySqlComment::createTable();
        MySqlStory::createTable();

        PostgreSqlUser::createTable();
        PostgreSqlPost::createTable();
        PostgreSqlComment::createTable();
        PostgreSqlStory::createTable();
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::$manager->setAsGlobal();
        self::$manager->bootEloquent();

        $this->app->bind('db', fn () => self::$manager->getDatabaseManager());
        $this->app->register(BulkUpsertServiceProvider::class);
    }

    public function assertDatabaseMissing($table, array $data, $connection = null)
    {
        $filters = [];
        $jsons = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($connection === 'mysql') {
                    $filters[$key] = DB::connection($connection)->raw(
                        sprintf("cast('%s' as json)", json_encode($value, JSON_THROW_ON_ERROR))
                    );
                } else {
                    $jsons[$key] = json_encode($value);
                }
            } else {
                $filters[$key] = $value;
            }
        }

        parent::assertDatabaseMissing($table, $filters, $connection);

        if (empty($jsons)) {
            return;
        }

        foreach ($jsons as $key => $json) {
            $hasRows = DB::connection($connection)
                ->table($table)
                ->where($filters)
                ->where(
                    DB::connection($connection)->raw($key . '::text'),
                    DB::connection($connection)->raw(
                        sprintf("cast('%s' as json)::text", $json)
                    )
                )
                ->exists();

            if ($hasRows) {
                $this->fail(
                    sprintf(
                        'Failed asserting that a row in the table [%s] matches the attributes %s',
                        $table,
                        json_encode(array_merge($filters, [$key => $json]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    )
                );
            }
        }
    }

    protected function spyShouldHaveReceived(LegacyMockInterface|MockInterface $spy): VerificationDirector
    {
        return $spy->shouldHaveReceived('__invoke');
    }

    protected function spyShouldNotHaveReceived(LegacyMockInterface|MockInterface $spy): void
    {
        $spy->shouldNotHaveReceived('__invoke');
    }

    protected function assertDatabaseHas($table, array $data, $connection = null)
    {
        $filters = [];
        $jsons = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($connection === 'mysql') {
                    $filters[$key] = DB::connection($connection)->raw(
                        sprintf("cast('%s' as json)", json_encode($value, JSON_THROW_ON_ERROR))
                    );
                } else {
                    $jsons[$key] = json_encode($value);
                }
            } else {
                $filters[$key] = $value;
            }
        }

        parent::assertDatabaseHas($table, $filters, $connection);

        if (empty($jsons)) {
            return;
        }

        foreach ($jsons as $key => $json) {
            $hasRows = DB::connection($connection)
                ->table($table)
                ->where($filters)
                ->where(
                    DB::connection($connection)->raw($key . '::text'),
                    DB::connection($connection)->raw(
                        sprintf("cast('%s' as json)::text", $json)
                    )
                )
                ->exists();

            if (!$hasRows) {
                $this->fail(
                    sprintf(
                        'Failed asserting that a row in the table [%s] matches the attributes %s',
                        $table,
                        json_encode(array_merge($filters, [$key => $json]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    )
                );
            }
        }
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

        self::$manager = $manager;

        self::$manager->setAsGlobal();
        self::$manager->bootEloquent();
    }
}
