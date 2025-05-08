<?php

namespace Tests\Unit\Bulk\Delete;

use Carbon\Carbon;
use JsonException;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Exceptions\BulkBindingResolution;
use Tests\App\Models\Post;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class DatabaseTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @return void
     *
     * @throws JsonException
     * @throws BulkException
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
     * @return void
     *
     * @throws JsonException
     * @throws BulkException
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
     * @param string $method
     *
     * @return void
     *
     * @throws BulkException
     * @throws JsonException
     * @throws BulkBindingResolution
     *
     * @dataProvider postModelsDataProvider
     */
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

    public static function postModelsDataProvider(): array
    {
        return [
            'delete' => ['delete'],
            'forceDelete' => ['forceDelete'],
        ];
    }
}
