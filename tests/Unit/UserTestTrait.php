<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Tests\App\Features\UserGenerator;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;

/**
 * Helpers for the tests which work with the user model.
 *
 * The trait builds the user generator, freezes the clock and gives the tests a set of
 * assertions which compare a user with its row in the database.
 *
 * @internal
 */
trait UserTestTrait
{
    /**
     * The generator which builds the users for the tests.
     *
     * @var \Tests\App\Features\UserGenerator
     */
    protected UserGenerator $userGenerator;

    /**
     * Set the test environment up.
     *
     * The clock is frozen, so the tests can compare the timestamps, and the listeners
     * of the observer are dropped.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->userGenerator = new UserGenerator();
        Carbon::setTestNow(Carbon::now());
        Observer::flush();
    }

    /**
     * Assert that the user was created just now.
     *
     * The row has to hold every attribute of the model. The created at and the updated
     * at have to hold the frozen current time.
     *
     * @param \Tests\App\Models\User $user
     *
     * @return void
     */
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
            'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => $user->deleted_at?->toDateTimeString(),
        ], $user->getConnectionName());
    }

    /**
     * Assert that the row of the user matches all of its attributes.
     *
     * Unlike {@see userWasCreated()} the timestamps are taken from the model itself, so
     * the method also works for a user which was written some time ago.
     *
     * @param \Tests\App\Models\User $user
     *
     * @return void
     */
    protected function userExists(User $user): void
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
            'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
            'created_at' => $user->created_at->toDateTimeString(),
            'updated_at' => $user->updated_at->toDateTimeString(),
            'deleted_at' => $user->deleted_at?->toDateTimeString(),
        ], $user->getConnectionName());
    }

    /**
     * Assert that the table has no user with this email.
     *
     * @param \Tests\App\Models\User $user
     *
     * @return void
     */
    protected function userDoesNotExist(User $user): void
    {
        self::assertDatabaseMissing($user->getTable(), [
            'email' => $user->email,
        ], $user->getConnectionName());
    }

    /**
     * Assert that the user was soft deleted.
     *
     * The row still holds every other attribute of the model, but a row with the same
     * email and an empty deleted at may not exist any more.
     *
     * @param \Tests\App\Models\User $user
     *
     * @return void
     */
    protected function userWasSoftDeleted(User $user): void
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
            'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
            'created_at' => $user->created_at->toDateTimeString(),
            'updated_at' => $user->updated_at->toDateTimeString(),
        ], $user->getConnectionName());

        self::assertDatabaseMissing($user->getTable(), [
            'email' => $user->email,
            'deleted_at' => null,
        ], $user->getConnectionName());
    }

    /**
     * Assert that the user is still there and its deleted at is empty.
     *
     * @param \Tests\App\Models\User $user
     *
     * @return void
     */
    protected function userWasNotDeleted(User $user): void
    {
        self::assertDatabaseHas($user->getTable(), [
            'id' => $user->id,
            'email' => $user->email,
            'deleted_at' => null,
        ], $user->getConnectionName());
    }

    /**
     * Assert that the user was updated just now.
     *
     * The created at stays the one of the model, but the updated at has to hold the
     * frozen current time.
     *
     * @param \Tests\App\Models\User $user
     *
     * @return void
     */
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
            'birthday' => $user->birthday?->toDateString(),
            'phones' => $user->phones,
            'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
            'created_at' => $user->created_at->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => $user->deleted_at?->toDateTimeString(),
        ], $user->getConnectionName());
    }

    /**
     * Assert that the name of the user was not written into the table.
     *
     * The given model carries the new name, so a missing row means that the update did
     * not happen.
     *
     * @param \Tests\App\Models\User $user
     *
     * @return void
     */
    protected function userWasNotUpdated(User $user): void
    {
        self::assertDatabaseMissing($user->getTable(), [
            'id' => $user->id,
            'name' => $user->name,
        ], $user->getConnectionName());
    }

    /**
     * Assert that the user was not written into the table.
     *
     * @param \Tests\App\Models\User $user
     *
     * @return void
     */
    protected function userWasNotCreated(User $user): void
    {
        self::assertDatabaseMissing($user->getTable(), [
            'email' => $user->email,
        ], $user->getConnectionName());
    }

    /**
     * Assert that a user which the package gave back holds the expected values.
     *
     * The updated at has to be the frozen current time. When the expected user has no
     * created at, the current time is expected there as well. The id has to be filled.
     *
     * @param \Tests\App\Models\User $expect
     * @param \Tests\App\Models\User $actual
     *
     * @return void
     */
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
