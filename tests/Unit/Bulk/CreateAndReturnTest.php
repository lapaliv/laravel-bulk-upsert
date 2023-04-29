<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk;

use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

/**
 * @internal
 */
final class CreateAndReturnTest extends TestCase
{
    /**
     * @param Closure $callback
     *
     * @return void
     *
     * @dataProvider dataProviderBasic
     */
    public function testWasRecentlyCreated(Closure $callback): void
    {
        // arrange
        /** @var Collection $collection */
        [
            'collection' => $collection,
            'iterable' => $iterable,
        ] = $callback();
        Carbon::setTestNow(Carbon::now());
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk(1);

        // act
        $result = $sut->createAndReturn($iterable());

        // assert
        self::assertCount($collection->count(), $result);
        $result->each(
            fn (User $user) => self::assertTrue($user->wasRecentlyCreated)
        );
    }

    /**
     * @param Closure $callback
     *
     * @return void
     *
     * @dataProvider dataProviderBasic
     */
    public function testBasic(Closure $callback): void
    {
        // arrange
        /** @var Collection $collection */
        [
            'collection' => $collection,
            'iterable' => $iterable,
        ] = $callback();
        Carbon::setTestNow(Carbon::now());
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $result = $sut->createAndReturn($iterable());

        // assert
        self::assertCount($collection->count(), $result);
        $collection->each(
            function (User $user) use ($result): void {
                /** @var User $resultUser */
                $resultUser = $result->where('email', $user->email)->first();
                self::assertNotNull($resultUser);
                self::assertNotNull($resultUser->id);
                self::assertEquals($user->name, $resultUser->name);
                self::assertEquals($user->gender, $resultUser->gender);
                self::assertEquals($user->avatar, $resultUser->avatar);
                self::assertEquals($user->posts_count, $resultUser->posts_count);
                self::assertEquals($user->is_admin, $resultUser->is_admin);
                self::assertEquals($user->balance, $resultUser->balance);
                self::assertEquals($user->birthday, $resultUser->birthday);
                self::assertEquals($user->phones, $resultUser->phones);
                self::assertEquals($user->last_visited_at, $resultUser->last_visited_at);
                self::assertNotNull($resultUser->created_at);
                self::assertNotNull($resultUser->updated_at);
                self::assertNull($resultUser->deleted_at);

                $this->assertDatabaseHas($resultUser->getTable(), [
                    'id' => $resultUser->id,
                ], $resultUser->getConnectionName());
            }
        );
    }

    public function dataProviderBasic(): array
    {
        return [
            'collection' => [
                function (): array {
                    $users = MySqlUser::factory()
                        ->count(5)
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
                        ->count(5)
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
                        ->count(5)
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
