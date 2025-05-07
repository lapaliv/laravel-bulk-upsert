<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\BulkBuilderTrait;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Observers\Observer;
use Lapaliv\BulkUpsert\Tests\TestCaseWrapper;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class SelectAndUpdateManyTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
