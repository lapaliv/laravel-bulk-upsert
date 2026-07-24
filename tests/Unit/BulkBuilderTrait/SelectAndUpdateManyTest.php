<?php

namespace Tests\Unit\BulkBuilderTrait;

use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * The `selectAndUpdateMany()` method of the builder, verified through the public bulk API.
 *
 * The method takes an Eloquent query and a set of values, selects the rows which match the
 * query and writes the values to every one of them.
 *
 * @internal
 */
class SelectAndUpdateManyTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * Every row matched by the query gets the new values when no observer is registered.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testWithoutEvents(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        $fakeUser = $this->userGenerator->makeOne();
        $values = [
            'name' => $fakeUser->name,
            'gender' => $fakeUser->gender,
            'avatar' => $fakeUser->avatar,
            'posts_count' => $fakeUser->posts_count,
            'is_admin' => $fakeUser->is_admin,
            'balance' => $fakeUser->balance,
            'birthday' => $fakeUser->birthday,
            'phones' => $fakeUser->phones,
            'last_visited_at' => $fakeUser->last_visited_at?->toDateTimeString(),
        ];
        $sut = User::query()
            ->whereIn('email', $users->pluck('email'));

        // act
        $sut->selectAndUpdateMany($values);

        // assert
        $users->each(
            function (User $user) use ($values) {
                $this->assertDatabaseHas(
                    $user->getTable(),
                    [
                        'email' => $user->email,
                        'name' => $values['name'],
                        'gender' => $values['gender']->value,
                        'avatar' => $values['avatar'],
                        'posts_count' => $values['posts_count'],
                        'is_admin' => $values['is_admin'],
                        'balance' => $values['balance'],
                        'birthday' => $values['birthday']?->toDateString(),
                        'phones' => $values['phones'],
                        'last_visited_at' => $values['last_visited_at'],
                    ],
                    $user->getConnectionName()
                );
            }
        );
    }

    /**
     * Every matched row gets the new values when an observer is registered.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testWithEventsById(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(10);
        $fakeUser = $this->userGenerator->makeOne();
        $values = [
            'name' => $fakeUser->name,
            'gender' => $fakeUser->gender,
            'avatar' => $fakeUser->avatar,
            'posts_count' => $fakeUser->posts_count,
            'is_admin' => $fakeUser->is_admin,
            'balance' => $fakeUser->balance,
            'birthday' => $fakeUser->birthday,
            'phones' => $fakeUser->phones,
            'last_visited_at' => $fakeUser->last_visited_at?->toDateTimeString(),
        ];
        User::observe(Observer::class);
        $sut = User::query()
            ->whereIn('email', $users->pluck('email'));

        // act
        $sut->selectAndUpdateMany($values);

        // assert
        $users->each(
            function (User $user) use ($values) {
                $this->assertDatabaseHas(
                    $user->getTable(),
                    [
                        'email' => $user->email,
                        'name' => $values['name'],
                        'gender' => $values['gender']->value,
                        'avatar' => $values['avatar'],
                        'posts_count' => $values['posts_count'],
                        'is_admin' => $values['is_admin'],
                        'balance' => $values['balance'],
                        'birthday' => $values['birthday']?->toDateString(),
                        'phones' => $values['phones'],
                        'last_visited_at' => $values['last_visited_at'],
                    ],
                    $user->getConnectionName()
                );
            }
        );
    }

    /**
     * Every matched row is still updated when the unique key is `email` and the chunk
     * size is smaller than the number of rows.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testWithEventsByEmail(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(10);
        $fakeUser = $this->userGenerator->makeOne();
        $values = [
            'name' => $fakeUser->name,
            'gender' => $fakeUser->gender,
            'avatar' => $fakeUser->avatar,
            'posts_count' => $fakeUser->posts_count,
            'is_admin' => $fakeUser->is_admin,
            'balance' => $fakeUser->balance,
            'birthday' => $fakeUser->birthday,
            'phones' => $fakeUser->phones,
            'last_visited_at' => $fakeUser->last_visited_at?->toDateTimeString(),
        ];
        User::observe(Observer::class);
        $sut = User::query()
            ->whereIn('email', $users->pluck('email'));

        // act
        $sut->selectAndUpdateMany($values, ['email'], 6);

        // assert
        $users->each(
            function (User $user) use ($values) {
                $this->assertDatabaseHas(
                    $user->getTable(),
                    [
                        'email' => $user->email,
                        'name' => $values['name'],
                        'gender' => $values['gender']->value,
                        'avatar' => $values['avatar'],
                        'posts_count' => $values['posts_count'],
                        'is_admin' => $values['is_admin'],
                        'balance' => $values['balance'],
                        'birthday' => $values['birthday']?->toDateString(),
                        'phones' => $values['phones'],
                        'last_visited_at' => $values['last_visited_at'],
                    ],
                    $user->getConnectionName()
                );
            }
        );
    }
}
