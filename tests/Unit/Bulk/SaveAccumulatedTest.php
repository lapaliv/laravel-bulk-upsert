<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCaseWrapper;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class SaveAccumulatedTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @return void
     *
     * @throws BulkException
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
