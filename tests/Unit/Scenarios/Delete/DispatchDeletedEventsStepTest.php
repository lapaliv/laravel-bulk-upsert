<?php

namespace Tests\Unit\Scenarios\Delete;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * The `deleted` family of events fired after the delete,
 * verified through the public bulk API.
 *
 * @internal
 */
final class DispatchDeletedEventsStepTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * A row vetoed by `deleting` gets no `deleted` event.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testNotTriggeringForTheVetoedRow(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(3);
        User::observe(Observer::class);

        // The vetoed row sits in the middle on purpose: the rows after it
        // have to keep being processed.
        $this->listenEventReturning(BulkEventEnum::Deleting, [true, false, true]);
        $listener = $this->listenEvent(BulkEventEnum::Deleted);

        // act
        User::query()->bulk()->delete($users);

        // assert
        self::spyShouldHaveReceived($listener)->twice();
    }

    /**
     * A chunk vetoed by `deletingMany` gets no `deleted` event at all.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testNotTriggeringForTheVetoedChunk(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        User::observe(Observer::class);
        $this->listenEventReturning(BulkEventEnum::DeletingMany, [false]);
        $listener = $this->listenEvent(BulkEventEnum::Deleted);

        // act
        User::query()->bulk()->delete($users);

        // assert
        self::spyShouldNotHaveReceived($listener);
    }

    /**
     * The model handed to the listener carries the very `deleted_at` value
     * that has been written to the database.
     *
     * The delete does not read the rows back, so the value has to be put on the models
     * by hand before the listeners see them.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDeletedModelCarriesTheDeletedAtValue(): void
    {
        // arrange
        $users = $this->userGenerator->createCollection(2);
        $deletedAtValues = [];
        User::observe(Observer::class);
        Observer::listen(
            BulkEventEnum::Deleted,
            function (User $user) use (&$deletedAtValues): void {
                $deletedAtValues[$user->id] = $user->deleted_at;
            }
        );

        // act
        User::query()->bulk()->delete($users);

        // assert
        self::assertCount(2, $deletedAtValues);

        foreach ($users as $user) {
            self::assertArrayHasKey($user->id, $deletedAtValues);
            self::assertNotNull($deletedAtValues[$user->id]);
            self::assertSame(
                Carbon::now()->toDateTimeString(),
                Carbon::parse($deletedAtValues[$user->id])->toDateTimeString(),
            );
        }
    }
}
