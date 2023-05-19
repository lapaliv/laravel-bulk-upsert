<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use JsonException;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpdateOnlyAndExceptTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws JsonException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testOnly(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['id'])
            ->updateOnly(['name']);

        // act
        $sut->update($users);

        // assert
        $users->each(
            function (User $user) {
                $this->assertDatabaseHas($user->table(), [
                    'id' => $user->id,
                    'name' => $user->name,
                ], $user->getConnectionName());

                $this->assertDatabaseMissing($user->table(), [
                    'id' => $user->id,
                    'gender' => $user->gender->value,
                    'avatar' => $user->avatar,
                    'posts_count' => $user->posts_count,
                    'is_admin' => $user->is_admin,
                    'balance' => $user->balance,
                    'birthday' => $user->birthday,
                    'phones' => $user->phones,
                    'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString(),
                ], $user->getConnectionName());
            }
        );
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
    public function testUpdateAllExcept(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['id'])
            ->updateAllExcept(['name']);

        // act
        $sut->update($users);

        // assert
        $users->each(
            function (User $user) {
                $this->assertDatabaseMissing($user->table(), [
                    'id' => $user->id,
                    'name' => $user->name,
                ], $user->getConnectionName());

                $this->assertDatabaseHas($user->table(), [
                    'id' => $user->id,
                    'gender' => $user->gender->value,
                    'avatar' => $user->avatar,
                    'posts_count' => $user->posts_count,
                    'is_admin' => $user->is_admin,
                    'balance' => $user->balance,
                    'birthday' => $user->birthday,
                    'phones' => $user->phones,
                    'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString(),
                ], $user->getConnectionName());
            }
        );
    }

    public function userModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class],
            'postgre' => [PostgreSqlUser::class],
        ];
    }
}
