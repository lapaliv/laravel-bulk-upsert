<?php

namespace Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\App;
use Lapaliv\BulkUpsert\Collections\BulkRowCollection;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Collection\UserCollection;
use Tests\App\Features\UserGenerator;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\App\Support\TestCallback;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;
use Mockery;

/**
 * The events which `update()` skips when an event running before the writing returns false.
 *
 * @internal
 */
final class UpdateBeforeWritingEventDependenciesTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * A row whose model event returns false is dropped, and the dependent events see only the
     * other row.
     *
     * @param \Closure $data
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     * @param array<string, \Lapaliv\BulkUpsert\Enums\BulkEventEnum[]> $dependencies
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('modelDataProvider')]
    public function testModelEventReturnsFalseSometimes(
        Closure $data,
        BulkEventEnum $event,
        array $dependencies
    ): void
    {
        // arrange
        $users = $data();
        User::observe(Observer::class);
        /** @var array<string, callable|\Mockery\LegacyMockInterface|\Mockery\MockInterface> $spies */
        $spies = [
            $event->value => Mockery::mock(TestCallback::class),
        ];
        $spies[$event->value]
            ->expects('__invoke')
            ->times(count($users))
            ->andReturnValues([false, true]);
        Observer::listen($event, $spies[$event->value]);

        foreach ($dependencies as $dependencyList) {
            foreach ($dependencyList as $dependency) {
                $spies[$dependency->value] = Mockery::spy(TestCallback::class, $dependency->value);
                Observer::listen($dependency, $spies[$dependency->value]);
            }
        }

        $sut = User::query()
            ->bulk()
            ->withTrashed()
            ->uniqueBy(['id']);

        // act
        $sut->update($users);

        // assert
        foreach ($dependencies['model'] as $dependency) {
            self::spyShouldHaveReceived($spies[$dependency->value])
                ->once()
                ->withArgs(
                    static function (User $user) use ($users): bool {
                        return $user->id === $users[1]['id'];
                    }
                );
        }

        foreach ($dependencies['collection'] as $dependency) {
            self::spyShouldHaveReceived($spies[$dependency->value])
                ->once()
                ->withArgs(
                    static function (UserCollection $actualUsers, BulkRowCollection $bulkRows) use ($users): bool {
                        return $actualUsers->count() === 1
                            && $bulkRows->count() === 1
                            && $actualUsers->get(0)->id === $users[1]['id']
                            && $bulkRows->get(0)->getOriginal() === $users[1]
                            && $bulkRows->get(0)->getModel()->id === $users[1]['id'];
                    }
                );
        }
    }

    /**
     * When a model event returns false for every row, none of the dependent events is called.
     *
     * @param \Closure $data
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     * @param array<string, \Lapaliv\BulkUpsert\Enums\BulkEventEnum[]> $dependencies
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('modelDataProvider')]
    public function testModelEventReturnsFalseAlways(
        Closure $data,
        BulkEventEnum $event,
        array $dependencies
    ): void
    {
        // arrange
        $users = $data();
        User::observe(Observer::class);
        /** @var array<string, callable|\Mockery\LegacyMockInterface|\Mockery\MockInterface> $spies */
        $spies = [
            $event->value => Mockery::mock(TestCallback::class),
        ];
        $spies[$event->value]
            ->expects('__invoke')
            ->times(count($users))
            ->andReturnFalse();
        Observer::listen($event, $spies[$event->value]);

        foreach ($dependencies as $dependencyList) {
            foreach ($dependencyList as $dependency) {
                $spies[$dependency->value] = Mockery::spy(TestCallback::class, $dependency->value);
                Observer::listen($dependency, $spies[$dependency->value]);
            }
        }

        $sut = User::query()
            ->bulk()
            ->withTrashed()
            ->uniqueBy(['id']);

        // act
        $sut->update($users);

        // assert
        foreach ($dependencies['model'] as $dependency) {
            self::spyShouldNotHaveReceived($spies[$dependency->value]);
        }

        foreach ($dependencies['collection'] as $dependency) {
            self::spyShouldNotHaveReceived($spies[$dependency->value]);
        }
    }

    /**
     * When a collection event returns false, none of the dependent events is called.
     *
     * @param \Closure $data
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     * @param array<string, \Lapaliv\BulkUpsert\Enums\BulkEventEnum[]> $dependencies
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('collectionDataProvider')]
    public function testCollectionEventReturnsFalse(
        Closure $data,
        BulkEventEnum $event,
        array $dependencies,
    ): void
    {
        // arrange
        $users = $data();
        User::observe(Observer::class);
        /** @var array<string, callable|\Mockery\LegacyMockInterface|\Mockery\MockInterface> $spies */
        $spies = [
            $event->value => Mockery::mock(TestCallback::class),
        ];
        $spies[$event->value]
            ->expects('__invoke')
            ->once()
            ->andReturnFalse();
        Observer::listen($event, $spies[$event->value]);

        foreach ($dependencies as $dependencyList) {
            foreach ($dependencyList as $dependency) {
                $spies[$dependency->value] = Mockery::spy(TestCallback::class, $dependency->value);
                Observer::listen($dependency, $spies[$dependency->value]);
            }
        }

        $sut = User::query()
            ->bulk()
            ->withTrashed()
            ->uniqueBy(['id']);

        // act
        $sut->update($users);

        // assert
        foreach ($dependencies['model'] as $dependency) {
            self::spyShouldNotHaveReceived($spies[$dependency->value]);
        }

        foreach ($dependencies['collection'] as $dependency) {
            self::spyShouldNotHaveReceived($spies[$dependency->value]);
        }
    }

    /**
     * A model which is not dirty fires the saving events but no updating event.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDoNotFireUpdatingIfTheModelIsNotDirty(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        User::observe(Observer::class);
        $savingSpy = Mockery::spy(TestCallback::class);
        $savingManySpy = Mockery::spy(TestCallback::class);
        $updatingSpy = Mockery::spy(TestCallback::class);
        $updatingManySpy = Mockery::spy(TestCallback::class);

        Observer::listen(BulkEventEnum::Saving, $savingSpy);
        Observer::listen(BulkEventEnum::SavingMany, $savingManySpy);
        Observer::listen(BulkEventEnum::Updating, $updatingSpy);
        Observer::listen(BulkEventEnum::UpdatingMany, $updatingManySpy);

        $sut = User::query()
            ->bulk()
            ->uniqueBy(['id']);

        // act
        $sut->update($users);

        // assert
        self::spyShouldHaveReceived($savingSpy);
        self::spyShouldHaveReceived($savingManySpy);
        self::spyShouldNotHaveReceived($updatingSpy);
        self::spyShouldNotHaveReceived($updatingManySpy);
    }

    /**
     * The model events which run before the writing, with the events which depend on them.
     *
     * @return array<string, array{\Closure, \Lapaliv\BulkUpsert\Enums\BulkEventEnum, array<string, \Lapaliv\BulkUpsert\Enums\BulkEventEnum[]>}>
     */
    public static function modelDataProvider(): array
    {
        return [
            'saving' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(2)
                        ->toArray();
                },
                BulkEventEnum::Saving,
                [
                    'model' => [
                        BulkEventEnum::Saved,
                        BulkEventEnum::Updating,
                        BulkEventEnum::Updated,
                    ],
                    'collection' => [
                        BulkEventEnum::SavedMany,
                        BulkEventEnum::UpdatingMany,
                        BulkEventEnum::UpdatedMany,
                    ],
                ],
            ],
            'updating' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(2)
                        ->toArray();
                },
                BulkEventEnum::Updating,
                [
                    'model' => [
                        BulkEventEnum::Updated,
                    ],
                    'collection' => [
                        BulkEventEnum::UpdatingMany,
                        BulkEventEnum::UpdatedMany,
                    ],
                ],
            ],
            'saving && deleting' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(2, [], ['deleted_at' => Carbon::now()])
                        ->toArray();
                },
                BulkEventEnum::Saving,
                [
                    'model' => [
                        BulkEventEnum::Saved,
                        BulkEventEnum::Updating,
                        BulkEventEnum::Updated,
                        BulkEventEnum::Deleting,
                        BulkEventEnum::Deleted,
                    ],
                    'collection' => [
                        BulkEventEnum::SavedMany,
                        BulkEventEnum::UpdatingMany,
                        BulkEventEnum::UpdatedMany,
                        BulkEventEnum::DeletingMany,
                        BulkEventEnum::DeletedMany,
                    ],
                ],
            ],
            'updating && deleting' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(2, [], ['deleted_at' => Carbon::now()])
                        ->toArray();
                },
                BulkEventEnum::Updating,
                [
                    'model' => [
                        BulkEventEnum::Updated,
                    ],
                    'collection' => [
                        BulkEventEnum::UpdatingMany,
                        BulkEventEnum::UpdatedMany,
                    ],
                ],
            ],
            'saving && restoring' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(
                            2,
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null]
                        )
                        ->toArray();
                },
                BulkEventEnum::Saving,
                [
                    'model' => [
                        BulkEventEnum::Saved,
                        BulkEventEnum::Updating,
                        BulkEventEnum::Updated,
                        BulkEventEnum::Restoring,
                        BulkEventEnum::Restored,
                    ],
                    'collection' => [
                        BulkEventEnum::SavedMany,
                        BulkEventEnum::UpdatingMany,
                        BulkEventEnum::UpdatedMany,
                        BulkEventEnum::RestoringMany,
                        BulkEventEnum::RestoredMany,
                    ],
                ],
            ],
            'updating && restoring' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(
                            2,
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null]
                        )
                        ->toArray();
                },
                BulkEventEnum::Updating,
                [
                    'model' => [
                        BulkEventEnum::Updated,
                    ],
                    'collection' => [
                        BulkEventEnum::UpdatingMany,
                        BulkEventEnum::UpdatedMany,
                    ],
                ],
            ],
            'deleting' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollection(2, ['deleted_at' => null])
                        ->each(
                            function (User $user) {
                                $user->deleted_at = Carbon::now();
                            }
                        )
                        ->toArray();
                },
                BulkEventEnum::Deleting,
                [
                    'model' => [
                        BulkEventEnum::Deleted,
                    ],
                    'collection' => [
                        BulkEventEnum::DeletingMany,
                        BulkEventEnum::DeletedMany,
                    ],
                ],
            ],
            'restoring' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollection(2, ['deleted_at' => Carbon::now()])
                        ->each(
                            function (User $user) {
                                $user->deleted_at = null;
                            }
                        )
                        ->toArray();
                },
                BulkEventEnum::Restoring,
                [
                    'model' => [
                        BulkEventEnum::Restored,
                    ],
                    'collection' => [
                        BulkEventEnum::RestoringMany,
                        BulkEventEnum::RestoredMany,
                    ],
                ],
            ],
        ];
    }

    /**
     * The collection events which run before the writing, with the events which depend on them.
     *
     * @return array<string, array{\Closure, \Lapaliv\BulkUpsert\Enums\BulkEventEnum, array<string, \Lapaliv\BulkUpsert\Enums\BulkEventEnum[]>}>
     */
    public static function collectionDataProvider(): array
    {
        return [
            'saving many' => [
                function () {
                    $userGenerator = App::make(UserGenerator::class);
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
                BulkEventEnum::SavingMany,
                [
                    'model' => [
                        BulkEventEnum::Saved,
                        BulkEventEnum::Updating,
                        BulkEventEnum::Updated,
                        BulkEventEnum::Deleting,
                        BulkEventEnum::Deleted,
                        BulkEventEnum::Restoring,
                        BulkEventEnum::Restored,
                    ],
                    'collection' => [
                        BulkEventEnum::SavedMany,
                        BulkEventEnum::UpdatingMany,
                        BulkEventEnum::UpdatedMany,
                        BulkEventEnum::DeletingMany,
                        BulkEventEnum::DeletedMany,
                        BulkEventEnum::RestoringMany,
                        BulkEventEnum::RestoredMany,
                    ],
                ],
            ],
            'updating many' => [
                function () {
                    $userGenerator = App::make(UserGenerator::class);
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
                BulkEventEnum::UpdatingMany,
                [
                    'model' => [
                        BulkEventEnum::Updated,
                    ],
                    'collection' => [
                        BulkEventEnum::UpdatedMany,
                    ],
                ],
            ],
            'deleting many' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(
                            3,
                            ['deleted_at' => null],
                            ['deleted_at' => Carbon::now()],
                        )
                        ->toArray();
                },
                BulkEventEnum::DeletingMany,
                [
                    'model' => [
                        BulkEventEnum::Deleted,
                    ],
                    'collection' => [
                        BulkEventEnum::DeletedMany,
                    ],
                ],
            ],
            'restoring many' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(
                            3,
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null],
                        )
                        ->toArray();
                },
                BulkEventEnum::RestoringMany,
                [
                    'model' => [
                        BulkEventEnum::Restored,
                    ],
                    'collection' => [
                        BulkEventEnum::RestoredMany,
                    ],
                ],
            ],
        ];
    }
}
