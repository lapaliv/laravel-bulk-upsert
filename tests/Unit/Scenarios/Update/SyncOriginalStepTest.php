<?php

namespace Tests\Unit\Scenarios\Update;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * After an update, the models the caller passed in must be "clean",
 * verified through the public bulk API.
 *
 * @internal
 */
final class SyncOriginalStepTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * An updated model has no pending changes left.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdatedModelIsClean(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);

        // act
        User::query()->bulk()->update($users);

        // assert
        self::assertFalse($users->get(0)->isDirty());
        self::assertFalse($users->get(1)->isDirty());
    }

    /**
     * A model whose save was cancelled stays dirty.
     *
     * Its attributes never reached the database, so marking it clean would hide
     * from the caller that the row still differs from the stored one.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCancelledModelStaysDirty(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(3);
        User::observe(Observer::class);

        // The vetoed row sits in the middle on purpose: the rows after it
        // have to keep being processed.
        $this->listenEventReturning(BulkEventEnum::Saving, [true, false, true]);

        // act
        User::query()->bulk()->update($users);

        // assert
        self::assertFalse($users->get(0)->isDirty());
        self::assertTrue($users->get(1)->isDirty());
        self::assertFalse($users->get(2)->isDirty());
    }
}
