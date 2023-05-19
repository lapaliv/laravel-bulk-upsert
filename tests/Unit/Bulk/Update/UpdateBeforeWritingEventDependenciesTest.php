<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\App;
use Lapaliv\BulkUpsert\Collection\BulkRows;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Features\UserGenerator;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
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
final class UpdateBeforeWritingEventDependenciesTest extends TestCase
{
    use UserTestTrait;

    /**
     * When one of model events sometimes returns false then its dependencies have not been called.
     *
     * @param class-string<User> $model
     * @param Closure $data
     * @param string $event
     * @param array $dependencies
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider modelDataProvider
     */
    public function testModelEventReturnsFalseSometimes(
        string $model,
        Closure $data,
        string $event,
        array $dependencies
    ): void {
        // arrange
        $users = $data();
        $model::observe(UserObserver::class);
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

        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['id']);

        // act
        $sut->update($users);

        // assert
        foreach ($dependencies['model'] as $dependency) {
            $this->spyShouldHaveReceived($spies[$dependency])
                ->once()
                ->withArgs(
                    static function (User $user) use ($users): bool {
                        return $user->id === $users[1]['id'];
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
                            && $actualUsers->get(0)->id === $users[1]['id']
                            && $bulkRows->get(0)->original === $users[1]
                            && $bulkRows->get(0)->model->id === $users[1]['id'];
                    }
                );
        }
    }

    /**
     * If one of model events always returns false then its dependencies have not been called.
     *
     * @param class-string<User> $model
     * @param Closure $data
     * @param string $event
     * @param array $dependencies
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider modelDataProvider
     */
    public function testModelEventReturnsFalseAlways(
        string $model,
        Closure $data,
        string $event,
        array $dependencies
    ): void {
        // arrange
        $users = $data();
        $model::observe(UserObserver::class);
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

        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['id']);

        // act
        $sut->update($users);

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
     * @param class-string<User> $model
     * @param Closure $data
     * @param string $event
     * @param array $dependencies
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider collectionDataProvider
     */
    public function testCollectionEventReturnsFalse(
        string $model,
        Closure $data,
        string $event,
        array $dependencies,
    ): void {
        // arrange
        $users = $data();
        $model::observe(UserObserver::class);
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

        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['id']);

        // act
        $sut->update($users);

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
        $target = [
            'saving' => [
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(2)
                        ->toArray();
                },
                BulkEventEnum::SAVING,
                [
                    'model' => [
                        BulkEventEnum::SAVED,
                        BulkEventEnum::UPDATING,
                        BulkEventEnum::UPDATED,
                    ],
                    'collection' => [
                        BulkEventEnum::SAVED_MANY,
                        BulkEventEnum::UPDATING_MANY,
                        BulkEventEnum::UPDATED_MANY,
                    ],
                ],
            ],
            'updating' => [
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(2)
                        ->toArray();
                },
                BulkEventEnum::UPDATING,
                [
                    'model' => [
                        BulkEventEnum::UPDATED,
                    ],
                    'collection' => [
                        BulkEventEnum::UPDATING_MANY,
                        BulkEventEnum::UPDATED_MANY,
                    ],
                ],
            ],
            'saving && deleting' => [
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(2, [], ['deleted_at' => Carbon::now()])
                        ->toArray();
                },
                BulkEventEnum::SAVING,
                [
                    'model' => [
                        BulkEventEnum::SAVED,
                        BulkEventEnum::UPDATING,
                        BulkEventEnum::UPDATED,
                        BulkEventEnum::DELETING,
                        BulkEventEnum::DELETED,
                    ],
                    'collection' => [
                        BulkEventEnum::SAVED_MANY,
                        BulkEventEnum::UPDATING_MANY,
                        BulkEventEnum::UPDATED_MANY,
                        BulkEventEnum::DELETING_MANY,
                        BulkEventEnum::DELETED_MANY,
                    ],
                ],
            ],
            'updating && deleting' => [
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(2, [], ['deleted_at' => Carbon::now()])
                        ->toArray();
                },
                BulkEventEnum::UPDATING,
                [
                    'model' => [
                        BulkEventEnum::UPDATED,
                    ],
                    'collection' => [
                        BulkEventEnum::UPDATING_MANY,
                        BulkEventEnum::UPDATED_MANY,
                    ],
                ],
            ],
            'saving && restoring' => [
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(
                            2,
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null]
                        )
                        ->toArray();
                },
                BulkEventEnum::SAVING,
                [
                    'model' => [
                        BulkEventEnum::SAVED,
                        BulkEventEnum::UPDATING,
                        BulkEventEnum::UPDATED,
                        BulkEventEnum::RESTORING,
                        BulkEventEnum::RESTORED,
                    ],
                    'collection' => [
                        BulkEventEnum::SAVED_MANY,
                        BulkEventEnum::UPDATING_MANY,
                        BulkEventEnum::UPDATED_MANY,
                        BulkEventEnum::RESTORING_MANY,
                        BulkEventEnum::RESTORED_MANY,
                    ],
                ],
            ],
            'updating && restoring' => [
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(
                            2,
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null]
                        )
                        ->toArray();
                },
                BulkEventEnum::UPDATING,
                [
                    'model' => [
                        BulkEventEnum::UPDATED,
                    ],
                    'collection' => [
                        BulkEventEnum::UPDATING_MANY,
                        BulkEventEnum::UPDATED_MANY,
                    ],
                ],
            ],
            'deleting' => [
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollection(2, ['deleted_at' => null])
                        ->each(
                            function (User $user) {
                                $user->deleted_at = Carbon::now();
                            }
                        )
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
            'restoring' => [
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollection(2, ['deleted_at' => Carbon::now()])
                        ->each(
                            function (User $user) {
                                $user->deleted_at = null;
                            }
                        )
                        ->toArray();
                },
                BulkEventEnum::RESTORING,
                [
                    'model' => [
                        BulkEventEnum::RESTORED,
                    ],
                    'collection' => [
                        BulkEventEnum::RESTORING_MANY,
                        BulkEventEnum::RESTORED_MANY,
                    ],
                ],
            ],
        ];

        $result = [];

        foreach ($this->userModels() as $type => $model) {
            foreach ($target as $key => $value) {
                $result[$key . ' && ' . $type] = [
                    $model,
                    function () use ($value, $model) {
                        return $value[0]($model);
                    },
                    ...array_slice($value, 1),
                ];
            }
        }

        return $result;
    }

    public function collectionDataProvider(): array
    {
        $target = [
            'saving many' => [
                function (string $model) {
                    $userGenerator = App::make(UserGenerator::class)
                        ->setModel($model);
                    $firstUser = $userGenerator->createOneAndDirty();
                    $secondUser = $userGenerator->createOneAndDirty(
                        ['deleted_at' => null],
                        ['deleted_at' => Carbon::now()],
                    );
                    $thirdUser = $userGenerator->createOneAndDirty(
                        ['deleted_at' => Carbon::now()],
                        ['deleted_at' => null],
                    );

                    return [
                        $firstUser->toArray(),
                        $secondUser->toArray(),
                        $thirdUser->toArray(),
                    ];
                },
                BulkEventEnum::SAVING_MANY,
                [
                    'model' => [
                        BulkEventEnum::SAVED,
                        BulkEventEnum::UPDATING,
                        BulkEventEnum::UPDATED,
                        BulkEventEnum::DELETING,
                        BulkEventEnum::DELETED,
                        BulkEventEnum::RESTORING,
                        BulkEventEnum::RESTORED,
                    ],
                    'collection' => [
                        BulkEventEnum::SAVED_MANY,
                        BulkEventEnum::UPDATING_MANY,
                        BulkEventEnum::UPDATED_MANY,
                        BulkEventEnum::DELETING_MANY,
                        BulkEventEnum::DELETED_MANY,
                        BulkEventEnum::RESTORING_MANY,
                        BulkEventEnum::RESTORED_MANY,
                    ],
                ],
            ],
            'updating many' => [
                function (string $model) {
                    $userGenerator = App::make(UserGenerator::class)
                        ->setModel($model);
                    $firstUser = $userGenerator->createOneAndDirty();
                    $secondUser = $userGenerator->createOneAndDirty(
                        ['deleted_at' => null],
                        ['deleted_at' => Carbon::now()],
                    );
                    $thirdUser = $userGenerator->createOneAndDirty(
                        ['deleted_at' => Carbon::now()],
                        ['deleted_at' => null],
                    );

                    return [
                        $firstUser->toArray(),
                        $secondUser->toArray(),
                        $thirdUser->toArray(),
                    ];
                },
                BulkEventEnum::UPDATING_MANY,
                [
                    'model' => [
                        BulkEventEnum::UPDATED,
                    ],
                    'collection' => [
                        BulkEventEnum::UPDATED_MANY,
                    ],
                ],
            ],
            'deleting many' => [
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(
                            3,
                            ['deleted_at' => null],
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
            'restoring many' => [
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(
                            3,
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null],
                        )
                        ->toArray();
                },
                BulkEventEnum::RESTORING_MANY,
                [
                    'model' => [
                        BulkEventEnum::RESTORED,
                    ],
                    'collection' => [
                        BulkEventEnum::RESTORED_MANY,
                    ],
                ],
            ],
        ];

        $result = [];

        foreach ($this->userModels() as $type => $model) {
            foreach ($target as $key => $value) {
                $result[$key . ' && ' . $type] = [
                    $model,
                    function () use ($value, $model) {
                        return $value[0]($model);
                    },
                    ...array_slice($value, 1),
                ];
            }
        }

        return $result;
    }

    public function userModels(): array
    {
        return [
            'mysql' => MySqlUser::class,
            'postgre' => PostgreSqlUser::class,
        ];
    }
}
