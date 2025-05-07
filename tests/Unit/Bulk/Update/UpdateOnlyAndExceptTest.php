<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCaseWrapper;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpdateOnlyAndExceptTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @return void
     *
     * @throws BulkException
     */
    public function testOnly(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
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
                    'birthday' => $user->birthday?->toDateString(),
                    'phones' => $user->phones,
                    'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString(),
                ], $user->getConnectionName());
            }
        );
    }

    /**
     * @return void
     *
     * @throws BulkException
     */
    public function testUpdateAllExcept(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
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
                    'birthday' => $user->birthday?->toDateString(),
                    'phones' => $user->phones,
                    'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString(),
                ], $user->getConnectionName());
            }
        );
    }
}
