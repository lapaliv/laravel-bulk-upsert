<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

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
use Mockery;

/**
 * @internal
 */
class UpdateAfterWritingEventsTest extends TestCase
{
    /**
     * @param Closure $data
     * @param array $events
     *
     * @return void
     *
     * @dataProvider modelDataProvider
     */
    public function testModel(Closure $data, array $events): void
    {
        // arrange
        /** @var UserCollection $users */
        $users = $data();
        $users = $users->keyBy('id');
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);
        MySqlUser::observe(UserObserver::class);

        $spies = [];

        foreach ($events as $event) {
            $spies[$event] = Mockery::spy(TestCallback::class, $event);
            UserObserver::listen($event, $spies[$event]);
        }

        // act
        $sut->update($users);

        // assert
        foreach ($events as $event) {
            $copiesUsers = clone $users;

            $this->spyShouldHaveReceived($spies[$event])
                ->times($users->count())
                ->withArgs(
                    function (User $user) use ($copiesUsers): bool {
                        self::assertArrayHasKey($user->id, $copiesUsers);
                        $copiesUsers->forget($user->id);

                        return true;
                    }
                );
        }
    }

    /**
     * @param Closure $data
     * @param string $event
     *
     * @return void
     *
     * @dataProvider collectionDataProvider
     */
    public function testCollection(Closure $data, string $event): void
    {
        // arrange
        /** @var UserCollection $users */
        $users = $data();
        $users = $users->keyBy('id');
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);
        $spy = Mockery::spy(TestCallback::class);
        MySqlUser::observe(UserObserver::class);
        UserObserver::listen($event, $spy);

        // act
        $sut->update($users);

        // assert
        $this->spyShouldHaveReceived($spy)
            ->once()
            ->withArgs(
                function (UserCollection $actualUsers, BulkRows $bulkRows) use ($users): bool {
                    return $actualUsers->count() === $users->count()
                        && $actualUsers->pluck('id')->sort()->join(',') === $users->pluck('id')->sort()->join(',')
                        && $bulkRows->count() === $users->count();
                }
            );
    }

    public function modelDataProvider(): array
    {
        return [
            'saved' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(2);
                },
                [BulkEventEnum::SAVED],
            ],
            'saved && deleted' => [
                function () {
                    return App::make(UserGenerator::class)->createCollectionAndDirty(
                        2,
                        ['deleted_at' => null],
                        ['deleted_at' => Carbon::now()],
                    );
                },
                [BulkEventEnum::SAVED, BulkEventEnum::DELETED],
            ],
            'saved && restored' => [
                function () {
                    return new UserCollection([
                        App::make(UserGenerator::class)->createOneAndDirty(
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null],
                        ),
                    ]);
                },
                [BulkEventEnum::SAVED, BulkEventEnum::RESTORED],
            ],
            'updated' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(2);
                },
                [BulkEventEnum::UPDATED],
            ],
            'updated && deleted' => [
                function () {
                    return new UserCollection([
                        App::make(UserGenerator::class)->createOneAndDirty(
                            ['deleted_at' => null],
                            ['deleted_at' => Carbon::now()],
                        ),
                    ]);
                },
                [BulkEventEnum::UPDATED, BulkEventEnum::DELETED],
            ],
            'updated && restored' => [
                function () {
                    return new UserCollection([
                        App::make(UserGenerator::class)->createOneAndDirty(
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null],
                        ),
                    ]);
                },
                [BulkEventEnum::UPDATED, BulkEventEnum::RESTORED],
            ],
            'deleted' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(
                            2,
                            ['deleted_at' => null],
                            ['deleted_at' => Carbon::now()],
                        );
                },
                [BulkEventEnum::DELETED],
            ],
            'restored' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(
                            2,
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null],
                        );
                },
                [BulkEventEnum::RESTORED],
            ],
        ];
    }

    public function collectionDataProvider(): array
    {
        return [
            'saved many' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(2);
                },
                BulkEventEnum::SAVED_MANY,
            ],
            'updated many' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(2);
                },
                BulkEventEnum::UPDATED_MANY,
            ],
            'deleted many' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(
                            2,
                            ['deleted_at' => null],
                            ['deleted_at' => Carbon::now()],
                        );
                },
                BulkEventEnum::DELETED_MANY,
            ],
            'restored many' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(
                            2,
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null],
                        );
                },
                BulkEventEnum::RESTORED_MANY,
            ],
        ];
    }
}
