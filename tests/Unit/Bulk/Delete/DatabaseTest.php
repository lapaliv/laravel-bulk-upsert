<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Delete;

use Carbon\Carbon;
use JsonException;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Exceptions\BulkBindingResolution;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlPost;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\Post;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlPost;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class DatabaseTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws JsonException
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testDeleteWithSoftDeleting(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollection(2);
        $sut = $model::query()
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
                'birthday' => $user->birthday,
                'phones' => $user->phones,
                'last_visited_at' => $user->last_visited_at,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
                'deleted_at' => Carbon::now()->toDateTimeString(),
            ], $user->getConnectionName());
        }
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws JsonException
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testForceDeleteWithSoftDeleting(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollection(2);
        $sut = $model::query()
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
                'birthday' => $user->birthday,
                'phones' => $user->phones,
                'last_visited_at' => $user->last_visited_at,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
                'deleted_at' => Carbon::now()->toDateTimeString(),
            ], $user->getConnectionName());
        }
    }

    /**
     * @param class-string<Post> $model
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
    public function testDeleteWithoutSoftDeleting(string $model, string $method): void
    {
        // arrange
        $posts = $model::factory()->count(2)->create();
        $sut = $model::query()->bulk();

        // act
        $sut->{$method}($posts);

        // assert
        foreach ($posts as $post) {
            $this->assertDatabaseMissing($post->getTable(), [
                'id' => $post->id,
            ], $post->getConnectionName());
        }
    }

    public function userModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class],
            'postgresql' => [MySqlUser::class],
        ];
    }

    public function postModelsDataProvider(): array
    {
        return [
            'mysql, delete' => [
                MySqlPost::class,
                'delete',
            ],
            'mysql, forceDelete' => [
                MySqlPost::class,
                'forceDelete',
            ],
            'postgresql, delete' => [
                PostgreSqlPost::class,
                'delete',
            ],
            'postgresql, forceDelete' => [
                PostgreSqlPost::class,
                'forceDelete',
            ],
        ];
    }
}
