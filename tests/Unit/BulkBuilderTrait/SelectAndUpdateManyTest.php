<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\BulkBuilderTrait;

use JsonException;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Observers\Observer;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class SelectAndUpdateManyTest extends TestCase
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
    public function testWithoutEvents(string $model): void
    {
        // arrange
        $this->userGenerator->setModel($model);
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
        $sut = $model::query()
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
                        'birthday' => $values['birthday'],
                        'phones' => $values['phones'],
                        'last_visited_at' => $values['last_visited_at'],
                    ],
                    $user->getConnectionName()
                );
            }
        );
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws JsonException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testWithEventsById(string $model): void
    {
        // arrange
        $this->userGenerator->setModel($model);
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
        $model::observe(Observer::class);
        $sut = $model::query()
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
                        'birthday' => $values['birthday'],
                        'phones' => $values['phones'],
                        'last_visited_at' => $values['last_visited_at'],
                    ],
                    $user->getConnectionName()
                );
            }
        );
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws JsonException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testWithEventsByEmail(string $model): void
    {
        // arrange
        $this->userGenerator->setModel($model);
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
        $model::observe(Observer::class);
        $sut = $model::query()
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
                        'birthday' => $values['birthday'],
                        'phones' => $values['phones'],
                        'last_visited_at' => $values['last_visited_at'],
                    ],
                    $user->getConnectionName()
                );
            }
        );
    }

    public function userModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class],
            'sql' => [PostgreSqlUser::class],
            'sql' => [SqLiteUser::class],
        ];
    }
}
