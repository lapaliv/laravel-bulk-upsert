<?php

namespace Tests\Unit\Scenarios\Update;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * Soft deleting and restoring a row as part of an update,
 * verified through the public bulk API.
 *
 * An update carries the `deleted_at` column like any other attribute, so filling it in soft
 * deletes the row and clearing it restores the row. Both transitions can be vetoed by a listener,
 * and a veto must drop only that one column from the query — the rest of the row still has to be
 * written.
 *
 * @internal
 */
final class DispatchDeletingAndRestoringEventsStepTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * Filling in `deleted_at` through an update soft deletes the row.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSoftDeleteThroughUpdate(): void
    {
        // arrange
        $user = $this->userGenerator->createOne(['deleted_at' => null]);
        $user->name = 'updated name';
        $user->deleted_at = Carbon::now();

        // act
        User::query()->bulk()->update([$user]);

        // assert
        self::assertDatabaseMissing($user->getTable(), [
            'id' => $user->id,
            'deleted_at' => null,
        ], $user->getConnectionName());
    }

    /**
     * A vetoed `deleting` leaves `deleted_at` alone but still writes the other columns.
     *
     * Dropping the whole row from the query instead would silently lose the rest of the
     * caller's changes, which the listener never objected to.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testVetoedSoftDeleteKeepsTheRestOfTheUpdate(): void
    {
        // arrange
        $user = $this->userGenerator->createOne(['deleted_at' => null]);
        $user->name = 'updated name';
        $user->deleted_at = Carbon::now();
        User::observe(Observer::class);
        $this->listenEventReturning(BulkEventEnum::Deleting, [false]);

        // act
        User::query()->bulk()->update([$user]);

        // assert
        self::assertDatabaseHas($user->getTable(), [
            'id' => $user->id,
            'name' => 'updated name',
            'deleted_at' => null,
        ], $user->getConnectionName());
    }

    /**
     * Clearing `deleted_at` through an update restores the row.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRestoreThroughUpdate(): void
    {
        // arrange
        $user = $this->userGenerator->createOne(['deleted_at' => Carbon::now()]);
        $user->name = 'updated name';
        $user->deleted_at = null;

        // act
        User::query()->bulk()->update([$user]);

        // assert
        self::assertDatabaseHas($user->getTable(), [
            'id' => $user->id,
            'name' => 'updated name',
            'deleted_at' => null,
        ], $user->getConnectionName());
    }

    /**
     * A vetoed `restoring` leaves `deleted_at` alone but still writes the other columns.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testVetoedRestoreKeepsTheRestOfTheUpdate(): void
    {
        // arrange
        $deletedAt = Carbon::now()->subDay();
        $user = $this->userGenerator->createOne(['deleted_at' => $deletedAt]);
        $user->name = 'updated name';
        $user->deleted_at = null;
        User::observe(Observer::class);
        $this->listenEventReturning(BulkEventEnum::Restoring, [false]);

        // act
        User::query()->bulk()->update([$user]);

        // assert
        self::assertDatabaseHas($user->getTable(), [
            'id' => $user->id,
            'name' => 'updated name',
            'deleted_at' => $deletedAt->toDateTimeString(),
        ], $user->getConnectionName());
    }
}
