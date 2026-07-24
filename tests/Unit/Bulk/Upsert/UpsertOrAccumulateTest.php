<?php

namespace Tests\Unit\Bulk\Upsert;

use Tests\App\Collection\UserCollection;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * The moment when `upsertOrAccumulate()` flushes the accumulated rows to the table.
 *
 * @internal
 */
final class UpsertOrAccumulateTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * Nothing is written while the number of the rows stays below the chunk size.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testBigChunkSize(): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
        ]);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->upsertOrAccumulate($users);

        // assert
        $this->userWasNotUpdated($users->get(0));
        $this->userWasNotCreated($users->get(1));
    }

    /**
     * The rows are written as soon as they reach the chunk size.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSmallChunkSize(): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
        ]);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk($users->count());

        // act
        $sut->upsertOrAccumulate($users);

        // assert
        $this->userWasUpdated($users->get(0));
        $this->userWasCreated($users->get(1));
    }

    /**
     * Only a full chunk is written, and the rest of the rows stays in the accumulation.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSmallChunkSizeWithExtraCount(): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
            $this->userGenerator->makeOne(),
        ]);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk($users->count() - 1);

        // act
        $sut->upsertOrAccumulate($users);

        // assert
        $this->userWasUpdated($users->get(0));
        $this->userWasUpdated($users->get(1));
        $this->userWasCreated($users->get(2));
        $this->userWasNotCreated($users->get(3));
    }

    /**
     * `saveAccumulated()` writes the rows which are still waiting in the accumulation.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSaveAccumulated(): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
        ]);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->upsertOrAccumulate($users);

        // act
        $sut->saveAccumulated();

        // assert
        $this->userWasUpdated($users->get(0));
        $this->userWasCreated($users->get(1));
    }
}
