<?php

namespace Tests\Unit\Scenarios\Delete;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * Cancelling a delete through the `deleting` family of events,
 * verified through the public bulk API.
 *
 * Every listener of this family may veto the operation by returning `false`. The per-model
 * events veto a single row, the `-Many` events veto the whole chunk, and a vetoed row must reach
 * neither the query nor the events ending with '-ed'.
 *
 * @internal
 */
final class DispatchDeletingEventsStepTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * `deleting` returning false keeps exactly the row it was called with.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDeletingCancellationKeepsTheVetoedRow(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(3);
        User::observe(Observer::class);

        // The vetoed row sits in the middle on purpose: the rows after it
        // have to keep being processed.
        $this->listenEventReturning(BulkEventEnum::Deleting, [true, false, true]);

        // act
        User::query()->bulk()->delete($users);

        // assert
        $this->userWasSoftDeleted($users->get(0));
        $this->userWasNotDeleted($users->get(1));
        $this->userWasSoftDeleted($users->get(2));
    }

    /**
     * `deletingMany` returning false keeps every row of the chunk.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDeletingManyCancellationKeepsEveryRow(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        User::observe(Observer::class);
        $this->listenEventReturning(BulkEventEnum::DeletingMany, [false]);

        // act
        User::query()->bulk()->delete($users);

        // assert
        $this->userWasNotDeleted($users->get(0));
        $this->userWasNotDeleted($users->get(1));
    }

    /**
     * `forceDeleting` returning false keeps exactly the row it was called with.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testForceDeletingCancellationKeepsTheVetoedRow(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(3);
        User::observe(Observer::class);

        // The vetoed row sits in the middle on purpose: the rows after it
        // have to keep being processed.
        $this->listenEventReturning(BulkEventEnum::ForceDeleting, [true, false, true]);

        // act
        User::query()->bulk()->forceDelete($users);

        // assert
        $this->userDoesNotExist($users->get(0));
        $this->userWasNotDeleted($users->get(1));
        $this->userDoesNotExist($users->get(2));
    }

    /**
     * A row vetoed by `forceDeleting` never reaches `deleting`.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testForceDeletingCancellationStopsTheRowBeforeDeleting(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(3);
        User::observe(Observer::class);
        $this->listenEventReturning(BulkEventEnum::ForceDeleting, [true, false, true]);
        $listener = $this->listenEvent(BulkEventEnum::Deleting);

        // act
        User::query()->bulk()->forceDelete($users);

        // assert
        self::spyShouldHaveReceived($listener)->twice();
    }

    /**
     * `forceDeletingMany` returning false keeps every row of the chunk.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testForceDeletingManyCancellationKeepsEveryRow(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        User::observe(Observer::class);
        $this->listenEventReturning(BulkEventEnum::ForceDeletingMany, [false]);

        // act
        User::query()->bulk()->forceDelete($users);

        // assert
        $this->userWasNotDeleted($users->get(0));
        $this->userWasNotDeleted($users->get(1));
    }

    /**
     * A chunk vetoed by `forceDeletingMany` never reaches `deletingMany`.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testForceDeletingManyCancellationStopsTheChunkBeforeDeletingMany(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        User::observe(Observer::class);
        $this->listenEventReturning(BulkEventEnum::ForceDeletingMany, [false]);
        $listener = $this->listenEvent(BulkEventEnum::DeletingMany);

        // act
        User::query()->bulk()->forceDelete($users);

        // assert
        self::spyShouldNotHaveReceived($listener);
    }
}
