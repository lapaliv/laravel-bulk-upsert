<?php

namespace Tests\Unit\Bulk\Upsert;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Tests\App\Collection\UserCollection;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpsertOrAccumulateTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
