<?php

namespace Tests;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Mockery\VerificationDirector;
use stdClass;
use Tests\App\Models\Article;
use Tests\App\Models\Comment;
use Tests\App\Models\Post;
use Tests\App\Models\Story;
use Tests\App\Models\User;

/**
 * The test case which the tests of the package extend.
 *
 * It builds the tables of the test models once for the whole run and adds the
 * assertions which can also compare the json columns.
 */
abstract class TestCaseWrapper extends TestCase
{
    /**
     * Whether the tables of the test models were already built in this run.
     *
     * @var bool
     */
    private static bool $schemaIsRefreshed = false;

    /**
     * Set the test environment up.
     *
     * Before the first test of the run the tables of the test models are dropped and
     * created again. The later tests reuse them.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$schemaIsRefreshed) {
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

            self::$schemaIsRefreshed = true;
        }
    }

    /**
     * Assert that the given table has no row with the given attributes.
     *
     * The values which are arrays are not given to the parent method. They are encoded
     * to json and compared with the stored values row by row. The assertion fails as
     * soon as one row holds the same json.
     *
     * @param string $table
     * @param array $data
     * @param string|null $connection
     *
     * @return void
     */
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

    /**
     * Assert that the spy of a listener was called.
     *
     * The director which comes back lets the caller check the count of the calls and
     * the arguments of them.
     *
     * @param \Mockery\LegacyMockInterface|\Mockery\MockInterface $spy
     *
     * @return \Mockery\VerificationDirector
     */
    protected static function spyShouldHaveReceived(LegacyMockInterface|MockInterface $spy): VerificationDirector
    {
        return $spy->shouldHaveReceived('__invoke');
    }

    /**
     * Assert that the spy of a listener was never called.
     *
     * @param \Mockery\LegacyMockInterface|\Mockery\MockInterface $spy
     *
     * @return void
     */
    protected static function spyShouldNotHaveReceived(LegacyMockInterface|MockInterface $spy): void
    {
        $spy->shouldNotHaveReceived('__invoke');
    }

    /**
     * Assert that the given table has a row with the given attributes.
     *
     * The values which are arrays are not given to the parent method. They are encoded
     * to json and compared with the stored values row by row. The assertion fails when
     * no row holds the same json.
     *
     * @param string $table
     * @param array $data
     * @param string|null $connection
     *
     * @return void
     */
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
     * Resolve the given class or binding from the container.
     *
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
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function getFromContainer(string $id): mixed
    {
        return Container::getInstance()->get($id);
    }
}
