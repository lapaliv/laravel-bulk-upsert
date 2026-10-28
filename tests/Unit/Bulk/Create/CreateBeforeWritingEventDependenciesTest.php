<?php

namespace Tests\Unit\Bulk\Create;

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
 * The events which `create()` skips when an event running before the writing returns false.
 *
 * @internal
 */
final class CreateBeforeWritingEventDependenciesTest extends TestCaseWrapper
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
    public function testModelEventReturnsFalseSometimes(Closure $data, BulkEventEnum $event, array $dependencies): void
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
            ->uniqueBy(['email']);

        // act
        $sut->create($users);

        // assert
        foreach ($dependencies['model'] as $dependency) {
            self::spyShouldHaveReceived($spies[$dependency->value])
                ->once()
                ->withArgs(
                    static function (User $user) use ($users): bool {
                        return $user->email === $users[1]['email'];
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
                            && $actualUsers->get(0)->email === $users[1]['email']
                            && $bulkRows->get(0)->getOriginal() === $users[1]
                            && $bulkRows->get(0)->getModel()->email === $users[1]['email'];
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
    public function testModelEventReturnsFalseAlways(Closure $data, BulkEventEnum $event, array $dependencies): void
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
            ->uniqueBy(['email']);

        // act
        $sut->create($users);

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
    public function testCollectionEventReturnsFalse(Closure $data, BulkEventEnum $event, array $dependencies): void
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
            ->uniqueBy(['email']);

        // act
        $sut->create($users);

        // assert
        foreach ($dependencies['model'] as $dependency) {
            self::spyShouldNotHaveReceived($spies[$dependency->value]);
        }

        foreach ($dependencies['collection'] as $dependency) {
            self::spyShouldNotHaveReceived($spies[$dependency->value]);
        }
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
                        ->makeCollection(2)
                        ->toArray();
                },
                BulkEventEnum::Saving,
                [
                    'model' => [
                        BulkEventEnum::Saved,
                        BulkEventEnum::Creating,
                        BulkEventEnum::Created,
                    ],
                    'collection' => [
                        BulkEventEnum::SavedMany,
                        BulkEventEnum::CreatingMany,
                        BulkEventEnum::CreatedMany,
                    ],
                ],
            ],
            'creating' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->makeCollection(2)
                        ->toArray();
                },
                BulkEventEnum::Creating,
                [
                    'model' => [
                        BulkEventEnum::Created,
                    ],
                    'collection' => [
                        BulkEventEnum::CreatingMany,
                        BulkEventEnum::CreatedMany,
                    ],
                ],
            ],
            'saving && deleting' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->makeCollection(2, ['deleted_at' => Carbon::now()])
                        ->toArray();
                },
                BulkEventEnum::Saving,
                [
                    'model' => [
                        BulkEventEnum::Saved,
                        BulkEventEnum::Creating,
                        BulkEventEnum::Created,
                        BulkEventEnum::Deleting,
                        BulkEventEnum::Deleted,
                    ],
                    'collection' => [
                        BulkEventEnum::SavedMany,
                        BulkEventEnum::CreatingMany,
                        BulkEventEnum::CreatedMany,
                        BulkEventEnum::DeletingMany,
                        BulkEventEnum::DeletedMany,
                    ],
                ],
            ],
            'creating && deleting' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->makeCollection(2, ['deleted_at' => Carbon::now()])
                        ->toArray();
                },
                BulkEventEnum::Creating,
                [
                    'model' => [
                        BulkEventEnum::Created,
                        BulkEventEnum::Deleting,
                        BulkEventEnum::Deleted,
                    ],
                    'collection' => [
                        BulkEventEnum::CreatingMany,
                        BulkEventEnum::CreatedMany,
                        BulkEventEnum::DeletingMany,
                        BulkEventEnum::DeletedMany,
                    ],
                ],
            ],
            'deleting' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->makeCollection(2, ['deleted_at' => Carbon::now()])
                        ->toArray();
                },
                BulkEventEnum::Deleting,
                [
                    'model' => [],
                    'collection' => [
                        BulkEventEnum::DeletingMany,
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
                    $firstUser = App::make(UserGenerator::class)->makeOne();
                    $secondUser = App::make(UserGenerator::class)->makeOne(
                        ['deleted_at' => Carbon::now()],
                    );

                    return [
                        $firstUser->toArray(),
                        $secondUser->toArray(),
                    ];
                },
                BulkEventEnum::SavingMany,
                [
                    'model' => [
                        BulkEventEnum::Saved,
                        BulkEventEnum::Creating,
                        BulkEventEnum::Created,
                        BulkEventEnum::Deleting,
                        BulkEventEnum::Deleted,
                    ],
                    'collection' => [
                        BulkEventEnum::SavedMany,
                        BulkEventEnum::CreatingMany,
                        BulkEventEnum::CreatedMany,
                        BulkEventEnum::DeletingMany,
                        BulkEventEnum::DeletedMany,
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
                BulkEventEnum::CreatingMany,
                [
                    'model' => [
                        BulkEventEnum::Created,
                        BulkEventEnum::Deleting,
                        BulkEventEnum::Deleted,
                    ],
                    'collection' => [
                        BulkEventEnum::CreatedMany,
                        BulkEventEnum::DeletingMany,
                        BulkEventEnum::DeletedMany,
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
                BulkEventEnum::DeletingMany,
                [
                    'model' => [],
                    'collection' => [],
                ],
            ],
        ];
    }
}
