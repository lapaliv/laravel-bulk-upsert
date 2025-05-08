<?php

namespace Tests\Unit\Bulk\Delete;

use JsonException;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Tests\App\Models\Post;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class DeleteOrAccumulateTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
     * @param string $method
     *
     * @return void
     *
     * @throws BulkException
     * @throws JsonException
     *
     * @dataProvider postModelsDataProvider
     */
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
     * @param string $method
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider postModelsDataProvider
     */
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

    public function postModelsDataProvider(): array
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
