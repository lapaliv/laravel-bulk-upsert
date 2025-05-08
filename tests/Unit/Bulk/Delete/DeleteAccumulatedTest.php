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
class DeleteAccumulatedTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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

    public function postModelsDataProvider(): array
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
