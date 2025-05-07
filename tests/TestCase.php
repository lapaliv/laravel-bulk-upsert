<?php

namespace Lapaliv\BulkUpsert\Tests;

use Dotenv\Dotenv;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Providers\BulkUpsertServiceProvider;
use Lapaliv\BulkUpsert\Tests\App\Models\Article;
use Lapaliv\BulkUpsert\Tests\App\Models\Comment;
use Lapaliv\BulkUpsert\Tests\App\Models\Post;
use Lapaliv\BulkUpsert\Tests\App\Models\Story;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Mockery\VerificationDirector;
use PDO;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use stdClass;

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

        // deleting tables
        Comment::dropTable();
        Post::dropTable();
        Story::dropTable();
        Article::dropTable();
        User::dropTable();

        User::createTable();
        Post::createTable();
        Comment::createTable();
        Story::createTable();
        Article::createTable();
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

    public function assertDatabaseMissing($table, array $data = [], $connection = null): void
    {
        $filters = [];
        $jsons = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $jsons[$key] = json_encode($value);
            } else {
                $filters[$key] = $value;
            }
        }

        parent::assertDatabaseMissing($table, $filters, $connection);

        if (empty($jsons)) {
            return;
        }

        foreach ($jsons as $key => $json) {
            $hasRows = false;
            DB::connection($connection)
                ->table($table)
                ->where($filters)
                ->orderBy('id')
                ->select('id', $key)
                ->each(
                    function (stdClass $row) use ($json, $key, &$hasRows): bool {
                        if ($row->{$key} === $json) {
                            $hasRows = true;
                        }

                        return !$hasRows;
                    }
                );

            if ($hasRows) {
                $this->fail(
                    sprintf(
                        'Failed asserting that a row in the table [%s] matches the attributes %s',
                        $table,
                        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    )
                );
            }
        }
    }

    protected static function spyShouldHaveReceived(LegacyMockInterface|MockInterface $spy): VerificationDirector
    {
        return $spy->shouldHaveReceived('__invoke');
    }

    protected static function spyShouldNotHaveReceived(LegacyMockInterface|MockInterface $spy): void
    {
        $spy->shouldNotHaveReceived('__invoke');
    }

    protected function assertDatabaseHas($table, array $data = [], $connection = null): void
    {
        $filters = [];
        $jsons = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $jsons[$key] = json_encode($value);
            } else {
                $filters[$key] = $value;
            }
        }

        parent::assertDatabaseHas($table, $filters, $connection);

        if (empty($jsons)) {
            return;
        }

        foreach ($jsons as $key => $json) {
            $hasRows = false;

            DB::connection($connection)
                ->table($table)
                ->where($filters)
                ->orderBy('id')
                ->select('id', $key)
                ->each(
                    function (stdClass $model) use (&$hasRows, $key, $json): bool {
                        if ($model->{$key} === $json) {
                            $hasRows = true;

                            return false;
                        }

                        return true;
                    }
                );

            if (!$hasRows) {
                $this->fail(
                    sprintf(
                        'Failed asserting that a row in the table [%s] matches the attributes %s',
                        $table,
                        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    )
                );
            }
        }
    }

    /**
     * @template T
     *
     * @param string $id
     *
     * @psalm-param class-string<T> $id
     *
     * @return mixed
     *
     * @psalm-return  T
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getFromContainer(string $id): mixed
    {
        return Container::getInstance()->get($id);
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
