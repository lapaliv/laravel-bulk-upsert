<?php

namespace Tests\Unit\Bulk\Delete;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\Post;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * The flushing of the accumulated rows by `deleteAccumulated()` and `forceDeleteAccumulated()`.
 *
 * @internal
 */
class DeleteAccumulatedTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * `deleteAccumulated()` soft deletes the rows which wait in the accumulation.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDeleteAccumulatedWithSoftDeleting(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        $sut = User::query()
            ->bulk()
            ->deleteOrAccumulate($users);

        // act
        $sut->deleteAccumulated();

        // assert
        $users->each(
            fn(User $user) => $this->userWasSoftDeleted($user)
        );
    }

    /**
     * `forceDeleteAccumulated()` removes the rows even when the model uses soft deleting.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testForceDeleteAccumulatedWithSoftDeleting(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        $sut = User::query()
            ->bulk()
            ->forceDeleteOrAccumulate($users);

        // act
        $sut->forceDeleteAccumulated();

        // assert
        $users->each(
            fn(User $user) => $this->userDoesNotExist($user)
        );
    }

    /**
     * A model without soft deleting loses its rows on both the soft and the forced path.
     *
     * @param string $accumulateMethod
     * @param string $deleteMethod
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     * @throws \JsonException
     */
    #[DataProvider('postModelsDataProvider')]
    public function testDeleteAccumulatedWithoutSoftDeleting(string $accumulateMethod, string $deleteMethod): void
    {
        // arrange
        $posts = Post::factory()
            ->count(2)
            ->create();
        $sut = Post::query()
            ->bulk()
            ->{$accumulateMethod}($posts);

        // act
        $sut->{$deleteMethod}();

        // assert
        $posts->each(
            fn(Post $post) => $this->assertDatabaseMissing($post->getTable(), [
                'id' => $post->id,
            ], $post->getConnectionName())
        );
    }

    /**
     * The pairs of the accumulating method and the method which flushes it.
     *
     * @return array<string, array{string, string}>
     */
    public static function postModelsDataProvider(): array
    {
        return [
            'not force' => [
                'deleteOrAccumulate',
                'deleteAccumulated',
            ],
            'force' => [
                'forceDeleteOrAccumulate',
                'forceDeleteAccumulated',
            ],
        ];
    }
}
