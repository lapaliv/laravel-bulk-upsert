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
class DeleteAccumulatedTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     *
     * @throws BulkException
     */
    public function testDeleteAccumulatedWithSoftDeleting(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollection(2);
        $sut = $model::query()
            ->bulk()
            ->deleteOrAccumulate($users);

        // act
        $sut->deleteAccumulated();

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
     * @dataProvider userModelsDataProvider
     *
     * @throws BulkException
     */
    public function testForceDeleteAccumulatedWithSoftDeleting(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollection(2);
        $sut = $model::query()
            ->bulk()
            ->forceDeleteOrAccumulate($users);

        // act
        $sut->forceDeleteAccumulated();

        // assert
        $users->each(
            fn (User $user) => $this->userDoesNotExist($user)
        );
    }

    /**
     * @param class-string<Post> $model
     * @param string $accumulateMethod
     * @param string $deleteMethod
     *
     * @return void
     *
     * @throws BulkException
     * @throws JsonException
     *
     * @dataProvider postModelsDataProvider
     */
    public function testDeleteAccumulatedWithoutSoftDeleting(
        string $model,
        string $accumulateMethod,
        string $deleteMethod,
    ): void {
        // arrange
        $posts = $model::factory()
            ->count(2)
            ->create();
        $sut = $model::query()
            ->bulk()
            ->{$accumulateMethod}($posts);

        // act
        $sut->{$deleteMethod}();

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
            'mysql' => [
                MySqlPost::class,
                'deleteOrAccumulate',
                'deleteAccumulated',
            ],
            'postgresql' => [
                PostgreSqlPost::class,
                'forceDeleteOrAccumulate',
                'forceDeleteAccumulated',
            ],
        ];
    }
}
