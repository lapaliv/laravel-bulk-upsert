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
use Mockery;

/**
 * The events which `update()` fires after the rows are written.
 *
 * @internal
 */
final class UpdateAfterWritingEventsTest extends TestCaseWrapper
{
    /**
     * Each expected model event is fired once per updated model.
     *
     * @param \Closure $data
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum[] $events
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('modelDataProvider')]
    public function testModel(Closure $data, array $events): void
    {
        // arrange
        /** @var \Tests\App\Collection\UserCollection $users */
        $users = $data();
        $users = $users->keyBy('id');
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);
        User::observe(Observer::class);

        $spies = [];

        foreach ($events as $event) {
            $spies[$event->value] = Mockery::spy(TestCallback::class, $event->value);
            Observer::listen($event, $spies[$event->value]);
        }

        // act
        $sut->update($users);

        // assert
        foreach ($events as $event) {
            $copiesUsers = clone $users;

            self::spyShouldHaveReceived($spies[$event->value])
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
     * The collection event is fired once with all of the updated models.
     *
     * @param \Closure $data
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('collectionDataProvider')]
    public function testCollection(Closure $data, BulkEventEnum $event): void
    {
        // arrange
        /** @var \Tests\App\Collection\UserCollection $users */
        $users = $data();
        $users = $users->keyBy('id');
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);
        $spy = Mockery::spy(TestCallback::class);
        User::observe(Observer::class);
        Observer::listen($event, $spy);

        // act
        $sut->update($users);

        // assert
        self::spyShouldHaveReceived($spy)
            ->once()
            ->withArgs(
                function (UserCollection $actualUsers, BulkRowCollection $bulkRows) use ($users): bool {
                    return $actualUsers->count() === $users->count()
                        && $actualUsers->pluck('id')->sort()->join(',') === $users->pluck('id')->sort()->join(',')
                        && $bulkRows->count() === $users->count();
                }
            );
    }

    /**
     * The rows to update, with the model events which they must fire afterwards.
     *
     * @return array<string, array{\Closure, \Lapaliv\BulkUpsert\Enums\BulkEventEnum[]}>
     */
    public static function modelDataProvider(): array
    {
        return [
            'saved' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(2);
                },
                [BulkEventEnum::Saved],
            ],
            'saved && deleted' => [
                function () {
                    return App::make(UserGenerator::class)->createCollectionAndDirty(
                        2,
                        ['deleted_at' => null],
                        ['deleted_at' => Carbon::now()],
                    );
                },
                [BulkEventEnum::Saved, BulkEventEnum::Deleted],
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
                [BulkEventEnum::Saved, BulkEventEnum::Restored],
            ],
            'updated' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(2);
                },
                [BulkEventEnum::Updated],
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
                [BulkEventEnum::Updated, BulkEventEnum::Deleted],
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
                [BulkEventEnum::Updated, BulkEventEnum::Restored],
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
                [BulkEventEnum::Deleted],
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
                [BulkEventEnum::Restored],
            ],
        ];
    }

    /**
     * The rows to update, with the collection event which they must fire afterwards.
     *
     * @return array<string, array{\Closure, \Lapaliv\BulkUpsert\Enums\BulkEventEnum}>
     */
    public static function collectionDataProvider(): array
    {
        return [
            'saved many' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(2);
                },
                BulkEventEnum::SavedMany,
            ],
            'updated many' => [
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(2);
                },
                BulkEventEnum::UpdatedMany,
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
                BulkEventEnum::DeletedMany,
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
                BulkEventEnum::RestoredMany,
            ],
        ];
    }
}
