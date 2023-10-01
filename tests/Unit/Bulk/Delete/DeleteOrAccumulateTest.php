<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Delete;

use JsonException;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlPost;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\Post;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlPost;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class DeleteOrAccumulateTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testDeleteWithSoftDeletingSmallChunk(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollection(2);
        $sut = $model::query()->bulk();

        // act
        $sut->deleteOrAccumulate($users);

        // assert
        $users->each(
            fn (User $user) => $this->userExists($user)
        );
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testForceDeleteWithSoftDeletingSmallChunk(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollection(2);
        $sut = $model::query()->bulk();

        // act
        $sut->forceDeleteOrAccumulate($users);

        // assert
        $users->each(
            fn (User $user) => $this->userExists($user)
        );
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testDeleteWithSoftDeletingBigChunk(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollection(2);
        $sut = $model::query()
            ->bulk()
            ->chunk($users->count());

        // act
        $sut->deleteOrAccumulate($users);

        // assert
        $users->each(
            fn (User $user) => $this->userWasSoftDeleted($user)
        );
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testForceDeleteWithSoftDeletingBigChunk(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollection(2);
        $sut = $model::query()
            ->bulk()
            ->chunk($users->count());

        // act
        $sut->forceDeleteOrAccumulate($users);

        // assert
        $users->each(
            fn (User $user) => $this->userDoesNotExist($user)
        );
    }

    /**
     * @param class-string<Post> $model
     * @param string $method
     *
     * @return void
     *
     * @throws BulkException
     * @throws JsonException
     *
     * @dataProvider postModelsDataProvider
     */
    public function testDeleteWithoutSoftDeletingSmallChunk(string $model, string $method): void
    {
        // arrange
        $posts = $model::factory()->count(2)->create();
        $sut = $model::query()->bulk();

        // act
        $sut->{$method}($posts);

        // assert
        $posts->each(
            fn (Post $post) => $this->assertDatabaseHas($post->getTable(), [
                'id' => $post->id,
            ], $post->getConnectionName())
        );
    }

    /**
     * @param class-string<Post> $model
     * @param string $method
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider postModelsDataProvider
     */
    public function testDeleteWithoutSoftDeletingBigChunk(string $model, string $method): void
    {
        // arrange
        $posts = $model::factory()->count(2)->create();
        $sut = $model::query()
            ->bulk()
            ->chunk($posts->count());

        // act
        $sut->{$method}($posts);

        // assert
        $posts->each(
            fn (Post $post) => $this->assertDatabaseMissing($post->getTable(), [
                'id' => $post->id,
            ], $post->getConnectionName())
        );
    }

    public function userModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class],
            'postgresql' => [PostgreSqlUser::class],
        ];
    }

    public function postModelsDataProvider(): array
    {
        return [
            'mysql, deleteOrAccumulate' => [
                MySqlPost::class,
                'deleteOrAccumulate',
            ],
            'mysql, forceDeleteOrAccumulate' => [
                MySqlPost::class,
                'forceDeleteOrAccumulate',
            ],
            'postgresql, deleteOrAccumulate' => [
                PostgreSqlPost::class,
                'deleteOrAccumulate',
            ],
            'postgresql, forceDeleteOrAccumulate' => [
                PostgreSqlPost::class,
                'forceDeleteOrAccumulate',
            ],
        ];
    }
}
