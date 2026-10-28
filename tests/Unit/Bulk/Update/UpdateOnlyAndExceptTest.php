<?php

namespace Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * The columns which `updateOnly()` and `updateAllExcept()` let `update()` write.
 *
 * @internal
 */
final class UpdateOnlyAndExceptTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * `updateOnly()` writes the listed column and leaves the other ones as they were.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
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
     * `updateAllExcept()` writes every column but the listed one.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
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
