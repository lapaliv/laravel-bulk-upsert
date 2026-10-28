<?php

namespace Tests\Unit\Bulk;

use Tests\App\Collection\UserCollection;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * The flushing of every kind of accumulated rows by a single `saveAccumulated()` call.
 *
 * @internal
 */
class SaveAccumulatedTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * The accumulated create, update and upsert rows are all written by one call.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function test(): void
    {
        // arrange
        $creatingUsers = $this->userGenerator->makeCollection(2);
        $updatingUsers = $this->userGenerator->createCollectionAndDirty(2);
        $upsertingUsers = new UserCollection([
            $this->userGenerator->makeOne(),
            $this->userGenerator->createOneAndDirty(),
        ]);

        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->orUniqueBy(['id'])
            ->createOrAccumulate($creatingUsers)
            ->updateOrAccumulate($updatingUsers)
            ->upsertOrAccumulate($upsertingUsers);

        // act
        $sut->saveAccumulated();

        // assert
        $creatingUsers->each(
            fn(User $user) => $this->userWasCreated($user)
        );
        $updatingUsers->each(
            fn(User $user) => $this->userWasUpdated($user)
        );
        $this->userWasCreated($upsertingUsers->get(0));
        $this->userWasUpdated($upsertingUsers->get(1));
    }
}
