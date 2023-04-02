<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk;

use Carbon\Carbon;
use Closure;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class CreateOrAccumulateTest extends TestCase
{
    /**
     * @param Closure $callback
     *
     * @return void
     *
     * @dataProvider dataProviderBasic
     */
    public function testBasicAccumulating(Closure $callback): void
    {
        // arrange
        [
            'collection' => $collection,
            'iterable' => $iterable,
        ] = $callback();
        Carbon::setTestNow(Carbon::now());
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->createOrAccumulate($iterable());

        // assert
        $collection->each(
            function (User $user) {
                $this->assertDatabaseMissing(MySqlUser::table(), [
                    'email' => $user->email,
                ], $user->getConnectionName());
            }
        );
    }

    /**
     * @param Closure $callback
     *
     * @return void
     *
     * @dataProvider dataProviderBasic
     */
    public function testBasicCreating(Closure $callback): void
    {
        // arrange
        [
            'collection' => $collection,
            'iterable' => $iterable,
        ] = $callback();
        Carbon::setTestNow(Carbon::now());
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk($collection->count());

        // act
        $sut->createOrAccumulate($iterable());

        // assert
        $collection->each(
            function (User $user) {
                $this->assertDatabaseHas(MySqlUser::table(), [
                    'name' => $user->name,
                    'email' => $user->email,
                    'gender' => $user->gender->value,
                    'avatar' => $user->avatar,
                    'posts_count' => $user->posts_count,
                    'is_admin' => $user->is_admin,
                    'balance' => $user->balance,
                    'birthday' => $user->birthday?->toDateString(),
                    'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString(),
                    'deleted_at' => null,
                ], $user->getConnectionName());

                $this->assertDatabaseMissing(MySqlUser::table(), [
                    'email' => $user->email,
                    'created_at' => null,
                    'updated_at' => null,
                ], $user->getConnectionName());

                $model = MySqlUser::query()
                    ->where('email', $user->email)
                    ->firstOrFail();

                self::assertEquals($user->phones, $model->phones);
            }
        );
    }

    public function testSoftDeleting(): void
    {
        // arrange
        Carbon::setTestNow(Carbon::now());
        $users = MySqlUser::factory()
            ->count(2)
            ->make([
                'deleted_at' => Carbon::now(),
            ]);
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk($users->count());

        // act
        $sut->createOrAccumulate($users);

        // assert
        $users->each(
            function (User $user) {
                $this->assertDatabaseHas(MySqlUser::table(), [
                    'name' => $user->name,
                    'email' => $user->email,
                    'gender' => $user->gender->value,
                    'avatar' => $user->avatar,
                    'posts_count' => $user->posts_count,
                    'is_admin' => $user->is_admin,
                    'balance' => $user->balance,
                    'birthday' => $user->birthday?->toDateString(),
                    'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString(),
                    'deleted_at' => Carbon::now()->toDateTimeString(),
                ], $user->getConnectionName());

                $model = MySqlUser::query()
                    ->onlyTrashed()
                    ->where('email', $user->email)
                    ->firstOrFail();

                self::assertEquals($user->phones, $model->phones);
            }
        );
    }

    public function dataProviderBasic(): array
    {
        return [
            'collection' => [
                function (): array {
                    $users = MySqlUser::factory()
                        ->count(2)
                        ->make();

                    return [
                        'collection' => $users,
                        'iterable' => function () use ($users) {
                            return $users;
                        },
                    ];
                },
            ],
            'array' => [
                function () {
                    $users = MySqlUser::factory()
                        ->count(2)
                        ->make();

                    return [
                        'collection' => $users,
                        'iterable' => function () use ($users) {
                            return $users->toArray();
                        },
                    ];
                },
            ],
            'generator' => [
                function () {
                    $users = MySqlUser::factory()
                        ->count(2)
                        ->make();

                    return [
                        'collection' => $users,
                        'iterable' => function () use ($users) {
                            foreach ($users as $user) {
                                yield $user;
                            }
                        },
                    ];
                },
            ],
        ];
    }
}
