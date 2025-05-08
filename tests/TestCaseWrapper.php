<?php

namespace Tests;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Mockery\VerificationDirector;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use stdClass;
use Tests\App\Models\Article;
use Tests\App\Models\Comment;
use Tests\App\Models\Post;
use Tests\App\Models\Story;
use Tests\App\Models\User;

abstract class TestCaseWrapper extends TestCase
{
    private bool $schemaIsRefreshed = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->schemaIsRefreshed) {
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

            $this->schemaIsRefreshed = true;
        }
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
}
