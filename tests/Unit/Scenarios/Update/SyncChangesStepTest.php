<?php

namespace Tests\Unit\Scenarios\Update;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * After an update, the models the caller passed in must report the changes the query made,
 * verified through the public bulk API.
 *
 * @internal
 */
final class SyncChangesStepTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * An updated model reports its changes through `getChanges()`.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdatedModelKeepsItsChanges(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);

        // act
        User::query()->bulk()->update($users);

        // assert
        self::assertArrayHasKey('name', $users->get(0)->getChanges());
        self::assertArrayHasKey('name', $users->get(1)->getChanges());
    }

    /**
     * A model whose save was cancelled reports no changes at all.
     *
     * Nothing has been written for it, so pretending otherwise would tell the caller
     * their row reached the database when it never did.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCancelledModelKeepsNoChanges(): void
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
        self::assertArrayHasKey('name', $users->get(0)->getChanges());
        self::assertArrayNotHasKey('name', $users->get(1)->getChanges());
        self::assertArrayHasKey('name', $users->get(2)->getChanges());
    }
}
