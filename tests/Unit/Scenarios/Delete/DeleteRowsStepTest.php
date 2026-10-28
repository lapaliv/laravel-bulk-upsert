<?php

namespace Tests\Unit\Scenarios\Delete;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * How the delete operation reports back whether it ran a query at all,
 * verified through the public bulk API.
 *
 * @internal
 */
final class DeleteRowsStepTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * No event ending with '-ed' is fired when every row of the chunk has been vetoed.
     *
     * When every row has been vetoed there is no query to run, and the algorithm stops
     * right there: none of the events ending with '-ed' is fired.
     *
     * The collection events are the interesting part — they are dispatched without an
     * "is the collection empty" guard, so a scenario that keeps going would fire
     * `deletedMany` with an empty collection.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testNoEndEventsWhenThereIsNothingToDelete(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        User::observe(Observer::class);
        $this->listenEventReturning(BulkEventEnum::DeletingMany, [false]);
        $deleted = $this->listenEvent(BulkEventEnum::Deleted);
        $deletedMany = $this->listenEvent(BulkEventEnum::DeletedMany);

        // act
        User::query()->bulk()->delete($users);

        // assert
        self::spyShouldNotHaveReceived($deleted);
        self::spyShouldNotHaveReceived($deletedMany);
        $this->userWasNotDeleted($users->get(0));
        $this->userWasNotDeleted($users->get(1));
    }

    /**
     * When at least one row survives the veto, the query runs and the '-ed' events follow.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testEndEventsWhenSomethingIsDeleted(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        User::observe(Observer::class);

        // approve #0, veto #1
        $this->listenEventReturning(BulkEventEnum::Deleting, [true, false]);
        $deletedMany = $this->listenEvent(BulkEventEnum::DeletedMany);

        // act
        User::query()->bulk()->delete($users);

        // assert
        self::spyShouldHaveReceived($deletedMany)->once();
    }
}
