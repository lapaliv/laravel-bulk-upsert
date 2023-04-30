<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Create;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\App;
use Lapaliv\BulkUpsert\Collection\BulkRows;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Features\UserGenerator;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Observers\UserObserver;
use Lapaliv\BulkUpsert\Tests\App\Support\TestCallback;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;
use Mockery;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;

/**
 * @internal
 */
final class CreateBeforeWritingEventDependenciesTest extends TestCase
{
    use UserTestTrait;

    /**
     * When one of model events sometimes returns false then its dependencies have not been called.
     *
     * @param Closure $data
     * @param string $event
     * @param array $dependencies
     *
     * @return void
     *
     * @dataProvider modelDataProvider
     */
    public function testModelEventReturnsFalseSometimes(Closure $data, string $event, array $dependencies): void
    {
        // arrange
        $users = $data();
        MySqlUser::observe(UserObserver::class);
        /** @var array<string, callable|LegacyMockInterface|MockInterface> $spies */
        $spies = [
            $event => Mockery::mock(TestCallback::class),
        ];
        $spies[$event]
            ->expects('__invoke')
            ->times(count($users))
            ->andReturnValues([false, true]);
        UserObserver::listen($event, $spies[$event]);

        foreach ($dependencies as $dependencyList) {
            foreach ($dependencyList as $dependency) {
                $spies[$dependency] = Mockery::spy(TestCallback::class, $dependency);
                UserObserver::listen($dependency, $spies[$dependency]);
            }
        }

        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->create($users);

        // assert
        foreach ($dependencies['model'] as $dependency) {
            $this->spyShouldHaveReceived($spies[$dependency])
                ->once()
                ->withArgs(
                    static function (User $user) use ($users): bool {
                        return $user->email === $users[1]['email'];
                    }
                );
        }

        foreach ($dependencies['collection'] as $dependency) {
            $this->spyShouldHaveReceived($spies[$dependency])
                ->once()
                ->withArgs(
                    static function (UserCollection $actualUsers, BulkRows $bulkRows) use ($users): bool {
                        return $actualUsers->count() === 1
                            && $bulkRows->count() === 1
                            && $actualUsers->get(0)->email === $users[1]['email']
                            && $bulkRows->get(0)->original === $users[1]
                            && $bulkRows->get(0)->model->email === $users[1]['email'];
                    }
                );
        }
    }

    /**
     * If one of model events always returns false then its dependencies have not been called.
     *
     * @param Closure $data
     * @param string $event
     * @param array $dependencies
     *
     * @return void
     *
     * @dataProvider modelDataProvider
     */
    public function testModelEventReturnsFalseAlways(Closure $data, string $event, array $dependencies): void
    {
        // arrange
        $users = $data();
        MySqlUser::observe(UserObserver::class);
        /** @var array<string, callable|LegacyMockInterface|MockInterface> $spies */
        $spies = [
            $event => Mockery::mock(TestCallback::class),
        ];
        $spies[$event]
            ->expects('__invoke')
            ->times(count($users))
            ->andReturnFalse();
        UserObserver::listen($event, $spies[$event]);

        foreach ($dependencies as $dependencyList) {
            foreach ($dependencyList as $dependency) {
                $spies[$dependency] = Mockery::spy(TestCallback::class, $dependency);
                UserObserver::listen($dependency, $spies[$dependency]);
            }
        }

        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->create($users);

        // assert
        foreach ($dependencies['model'] as $dependency) {
            $this->spyShouldNotHaveReceived($spies[$dependency]);
        }

        foreach ($dependencies['collection'] as $dependency) {
            $this->spyShouldNotHaveReceived($spies[$dependency]);
        }
    }

    /**
     * When one of collection events returns false then its dependencies have not been called.
     *
     * @param Closure $data
     * @param string $event
     * @param array $dependencies
     *
     * @return void
     *
     * @dataProvider collectionDataProvider
     */
    public function testCollectionEventReturnsFalse(Closure $data, string $event, array $dependencies): void
    {
        // arrange
        $users = $data();
        MySqlUser::observe(UserObserver::class);
        /** @var array<string, callable|LegacyMockInterface|MockInterface> $spies */
        $spies = [
            $event => Mockery::mock(TestCallback::class),
        ];
        $spies[$event]
            ->expects('__invoke')
            ->once()
            ->andReturnFalse();
        UserObserver::listen($event, $spies[$event]);

        foreach ($dependencies as $dependencyList) {
            foreach ($dependencyList as $dependency) {
                $spies[$dependency] = Mockery::spy(TestCallback::class, $dependency);
                UserObserver::listen($dependency, $spies[$dependency]);
            }
        }

        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->create($users);

        // assert
        foreach ($dependencies['model'] as $dependency) {
            $this->spyShouldNotHaveReceived($spies[$dependency]);
        }

        foreach ($dependencies['collection'] as $dependency) {
            $this->spyShouldNotHaveReceived($spies[$dependency]);
        }
    }

    public function modelDataProvider(): array
    {
        return [
            'saving' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->makeCollection(2)
                        ->toArray();
                },
                BulkEventEnum::SAVING,
                [
                    'model' => [
                        BulkEventEnum::SAVED,
                        BulkEventEnum::CREATING,
                        BulkEventEnum::CREATED,
                    ],
                    'collection' => [
                        BulkEventEnum::SAVED_MANY,
                        BulkEventEnum::CREATING_MANY,
                        BulkEventEnum::CREATED_MANY,
                    ],
                ],
            ],
            'creating' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->makeCollection(2)
                        ->toArray();
                },
                BulkEventEnum::CREATING,
                [
                    'model' => [
                        BulkEventEnum::CREATED,
                    ],
                    'collection' => [
                        BulkEventEnum::CREATING_MANY,
                        BulkEventEnum::CREATED_MANY,
                    ],
                ],
            ],
            'saving && deleting' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->makeCollection(2, ['deleted_at' => Carbon::now()])
                        ->toArray();
                },
                BulkEventEnum::SAVING,
                [
                    'model' => [
                        BulkEventEnum::SAVED,
                        BulkEventEnum::CREATING,
                        BulkEventEnum::CREATED,
                        BulkEventEnum::DELETING,
                        BulkEventEnum::DELETED,
                    ],
                    'collection' => [
                        BulkEventEnum::SAVED_MANY,
                        BulkEventEnum::CREATING_MANY,
                        BulkEventEnum::CREATED_MANY,
                        BulkEventEnum::DELETING_MANY,
                        BulkEventEnum::DELETED_MANY,
                    ],
                ],
            ],
            'creating && deleting' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->makeCollection(2, ['deleted_at' => Carbon::now()])
                        ->toArray();
                },
                BulkEventEnum::CREATING,
                [
                    'model' => [
                        BulkEventEnum::CREATED,
                        BulkEventEnum::DELETING,
                        BulkEventEnum::DELETED,
                    ],
                    'collection' => [
                        BulkEventEnum::CREATING_MANY,
                        BulkEventEnum::CREATED_MANY,
                        BulkEventEnum::DELETING_MANY,
                        BulkEventEnum::DELETED_MANY,
                    ],
                ],
            ],
            'deleting' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->makeCollection(2, ['deleted_at' => Carbon::now()])
                        ->toArray();
                },
                BulkEventEnum::DELETING,
                [
                    'model' => [
                        BulkEventEnum::DELETED,
                    ],
                    'collection' => [
                        BulkEventEnum::DELETING_MANY,
                        BulkEventEnum::DELETED_MANY,
                    ],
                ],
            ],
        ];
    }

    public function collectionDataProvider(): array
    {
        return [
            'saving many' => [
                function () {
                    $firstUser = App::make(UserGenerator::class)->makeOne();
                    $secondUser = App::make(UserGenerator::class)->makeOne(
                        ['deleted_at' => Carbon::now()],
                    );

                    return [
                        $firstUser->toArray(),
                        $secondUser->toArray(),
                    ];
                },
                BulkEventEnum::SAVING_MANY,
                [
                    'model' => [
                        BulkEventEnum::SAVED,
                        BulkEventEnum::CREATING,
                        BulkEventEnum::CREATED,
                        BulkEventEnum::DELETING,
                        BulkEventEnum::DELETED,
                    ],
                    'collection' => [
                        BulkEventEnum::SAVED_MANY,
                        BulkEventEnum::CREATING_MANY,
                        BulkEventEnum::CREATED_MANY,
                        BulkEventEnum::DELETING_MANY,
                        BulkEventEnum::DELETED_MANY,
                    ],
                ],
            ],
            'creating many' => [
                function () {
                    $firstUser = App::make(UserGenerator::class)->makeOne();
                    $secondUser = App::make(UserGenerator::class)->makeOne(
                        ['deleted_at' => Carbon::now()],
                    );

                    return [
                        $firstUser->toArray(),
                        $secondUser->toArray(),
                    ];
                },
                BulkEventEnum::CREATING_MANY,
                [
                    'model' => [
                        BulkEventEnum::CREATED,
                        BulkEventEnum::DELETING,
                        BulkEventEnum::DELETED,
                    ],
                    'collection' => [
                        BulkEventEnum::CREATED_MANY,
                        BulkEventEnum::DELETING_MANY,
                        BulkEventEnum::DELETED_MANY,
                    ],
                ],
            ],
            'deleting many' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->makeCollection(
                            3,
                            ['deleted_at' => Carbon::now()],
                        )
                        ->toArray();
                },
                BulkEventEnum::DELETING_MANY,
                [
                    'model' => [
                        BulkEventEnum::DELETED,
                    ],
                    'collection' => [
                        BulkEventEnum::DELETED_MANY,
                    ],
                ],
            ],
        ];
    }
}
