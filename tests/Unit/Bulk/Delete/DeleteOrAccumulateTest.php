<?php

namespace Tests\Unit\Bulk\Delete;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\Post;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * The moment when `deleteOrAccumulate()` and `forceDeleteOrAccumulate()` touch the table.
 *
 * @internal
 */
class DeleteOrAccumulateTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * The rows stay untouched while their number is below the default chunk size.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDeleteWithSoftDeletingSmallChunk(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        $sut = User::query()->bulk();

        // act
        $sut->deleteOrAccumulate($users);

        // assert
        $users->each(
            fn(User $user) => $this->userExists($user)
        );
    }

    /**
     * The forced path also keeps the rows while their number is below the chunk size.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testForceDeleteWithSoftDeletingSmallChunk(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        $sut = User::query()->bulk();

        // act
        $sut->forceDeleteOrAccumulate($users);

        // assert
        $users->each(
            fn(User $user) => $this->userExists($user)
        );
    }

    /**
     * The rows are soft deleted as soon as they reach the chunk size.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDeleteWithSoftDeletingBigChunk(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        $sut = User::query()
            ->bulk()
            ->chunk($users->count());

        // act
        $sut->deleteOrAccumulate($users);

        // assert
        $users->each(
            fn(User $user) => $this->userWasSoftDeleted($user)
        );
    }

    /**
     * The rows are removed for good as soon as they reach the chunk size.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testForceDeleteWithSoftDeletingBigChunk(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        $sut = User::query()
            ->bulk()
            ->chunk($users->count());

        // act
        $sut->forceDeleteOrAccumulate($users);

        // assert
        $users->each(
            fn(User $user) => $this->userDoesNotExist($user)
        );
    }

    /**
     * A model without soft deleting keeps its rows while they are below the chunk size.
     *
     * @param string $method
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     * @throws \JsonException
     */
    #[DataProvider('postModelsDataProvider')]
    public function testDeleteWithoutSoftDeletingSmallChunk(string $method): void
    {
        // arrange
        $posts = Post::factory()->count(2)->create();
        $sut = Post::query()->bulk();

        // act
        $sut->{$method}($posts);

        // assert
        $posts->each(
            fn(Post $post) => $this->assertDatabaseHas($post->getTable(), [
                'id' => $post->id,
            ], $post->getConnectionName())
        );
    }

    /**
     * A model without soft deleting loses its rows once they reach the chunk size.
     *
     * @param string $method
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('postModelsDataProvider')]
    public function testDeleteWithoutSoftDeletingBigChunk(string $method): void
    {
        // arrange
        $posts = Post::factory()->count(2)->create();
        $sut = Post::query()
            ->bulk()
            ->chunk($posts->count());

        // act
        $sut->{$method}($posts);

        // assert
        $posts->each(
            fn(Post $post) => $this->assertDatabaseMissing($post->getTable(), [
                'id' => $post->id,
            ], $post->getConnectionName())
        );
    }

    /**
     * The accumulating delete methods of the builder.
     *
     * @return array<string, array{string}>
     */
    public static function postModelsDataProvider(): array
    {
        return [
            'deleteOrAccumulate' => [
                'deleteOrAccumulate',
            ],
            'forceDeleteOrAccumulate' => [
                'forceDeleteOrAccumulate',
            ],
        ];
    }
}
