<?php

namespace Lapaliv\BulkUpsert\Tests\Unit;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Tests\App\Features\UserGenerator;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Observers\UserObserver;

/**
 * @internal
 */
trait UserTestTrait
{
    protected UserGenerator $userGenerator;

    public function setUp(): void
    {
        parent::setUp();

        $this->userGenerator = new UserGenerator();
        Carbon::setTestNow(Carbon::now());
        UserObserver::flush();
    }

    protected function userWasCreated(User $user): void
    {
        self::assertDatabaseHas($user->getTable(), [
            'email' => $user->email,
            'name' => $user->name,
            'gender' => $user->gender->value,
            'avatar' => $user->avatar,
            'posts_count' => $user->posts_count,
            'is_admin' => $user->is_admin,
            'balance' => $user->balance,
            'birthday' => $user->birthday?->toDateString(),
            'phones' => $user->phones,
            'last_visited_at' => $user->last_visited_at,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => $user->deleted_at?->toDateTimeString(),
        ], $user->getConnectionName());
    }

    protected function userWasUpdated(User $user): void
    {
        self::assertDatabaseHas($user->getTable(), [
            'id' => $user->id,
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
            'created_at' => $user->created_at->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => $user->deleted_at?->toDateTimeString(),
        ], $user->getConnectionName());
    }

    protected function userWasNotUpdated(User $user): void
    {
        self::assertDatabaseMissing($user->getTable(), [
            'id' => $user->id,
            'name' => $user->name,
        ], $user->getConnectionName());
    }

    protected function userWasNotCreated(User $user): void
    {
        self::assertDatabaseMissing($user->getTable(), [
            'email' => $user->email,
        ], $user->getConnectionName());
    }

    protected function returnedUserWasUpserted(User $expect, User $actual): void
    {
        self::assertEquals($expect->email, $actual->email);
        self::assertEquals($expect->name, $actual->name);
        self::assertEquals($expect->gender, $actual->gender);
        self::assertEquals($expect->avatar, $actual->avatar);
        self::assertEquals($expect->posts_count, $actual->posts_count);
        self::assertEquals($expect->is_admin, $actual->is_admin);
        self::assertEquals($expect->balance, $actual->balance);
        self::assertEquals($expect->birthday, $actual->birthday);
        self::assertEquals($expect->phones, $actual->phones);
        self::assertEquals($expect->last_visited_at, $actual->last_visited_at);
        self::assertEquals($expect->created_at ?? Carbon::now()->startOfSecond(), $actual->created_at);
        self::assertEquals(Carbon::now()->startOfSecond(), $actual->updated_at);
        self::assertEquals($expect->deleted_at, $actual->deleted_at);
        self::assertTrue($actual->id > 0);
    }
}
