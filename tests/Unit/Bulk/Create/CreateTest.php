<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Create;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class CreateTest extends TestCase
{
    use UserTestTrait;

    public function test(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->create($users);

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
                'phones' => $this->phonesToCast($user),
                'last_visited_at' => $user->last_visited_at,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
                'deleted_at' => $user->deleted_at?->toDateTimeString(),
            ], $user->getConnectionName());
        }
    }
}
