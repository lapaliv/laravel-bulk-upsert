<?php

namespace Tests\Unit\Bulk\Delete;

use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\Post;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * The rows which `delete()` and `forceDelete()` remove from the table.
 *
 * @internal
 */
class DeleteTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * A model with soft deleting keeps its row and gets the current time in `deleted_at`.
     *
     * @return void
     *
     * @throws \JsonException
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDeleteWithSoftDeleting(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->delete($users);

        // assert
        foreach ($users as $user) {
            $this->assertDatabaseHas($user->getTable(), [
                'email' => $user->email,
                'name' => $user->name,
                'gender' => $user->gender->value,
                'avatar' => $user->avatar,
                'posts_count' => $user->posts_count,
                'is_admin' => $user->is_admin,
                'balance' => $user->balance,
                'birthday' => $user->birthday?->toDateString(),
                'phones' => $user->phones,
                'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
                'deleted_at' => Carbon::now()->toDateTimeString(),
            ], $user->getConnectionName());
        }
    }

    /**
     * `forceDelete()` removes the row even when the model uses soft deleting.
     *
     * @return void
     *
     * @throws \JsonException
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testForceDeleteWithSoftDeleting(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->forceDelete($users);

        // assert
        foreach ($users as $user) {
            $this->assertDatabaseMissing($user->getTable(), [
                'email' => $user->email,
                'name' => $user->name,
                'gender' => $user->gender->value,
                'avatar' => $user->avatar,
                'posts_count' => $user->posts_count,
                'is_admin' => $user->is_admin,
                'balance' => $user->balance,
                'birthday' => $user->birthday?->toDateString(),
                'phones' => $user->phones,
                'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
                'deleted_at' => Carbon::now()->toDateTimeString(),
            ], $user->getConnectionName());
        }
    }

    /**
     * A model without soft deleting loses its rows on both the soft and the forced path.
     *
     * @param string $method
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     * @throws \JsonException
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkBindingResolution
     */
    #[DataProvider('postModelsDataProvider')]
    public function testDeleteWithoutSoftDeleting(string $method): void
    {
        // arrange
        $posts = Post::factory()->count(2)->create();
        $sut = Post::query()->bulk();

        // act
        $sut->{$method}($posts);

        // assert
        foreach ($posts as $post) {
            $this->assertDatabaseMissing($post->getTable(), [
                'id' => $post->id,
            ], $post->getConnectionName());
        }
    }

    /**
     * The delete methods of the builder.
     *
     * @return array<string, array{string}>
     */
    public static function postModelsDataProvider(): array
    {
        return [
            'delete' => ['delete'],
            'forceDelete' => ['forceDelete'],
        ];
    }
}
