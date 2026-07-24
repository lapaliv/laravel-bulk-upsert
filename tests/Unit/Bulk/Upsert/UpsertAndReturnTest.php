<?php

namespace Tests\Unit\Bulk\Upsert;

use Tests\App\Collection\UserCollection;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * The rows which `upsertAndReturn()` writes and the models which it gives back.
 *
 * @internal
 */
final class UpsertAndReturnTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * A known row is updated and an unknown row is inserted in the same call.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDatabase(): void
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
        $sut->upsertAndReturn($users);

        // assert
        $this->userWasUpdated($users->get(0));
        $this->userWasCreated($users->get(1));
    }

    /**
     * A batch which holds only unknown rows is inserted.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDatabaseCreateOnly(): void
    {
        // arrange
        $user = $this->userGenerator->makeOne();
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->upsertAndReturn([$user]);

        // assert
        $this->userWasCreated($user);
    }

    /**
     * A batch which holds only known rows is updated.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDatabaseUpdateOnly(): void
    {
        // arrange
        $user = $this->userGenerator->createOneAndDirty();
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->upsertAndReturn([$user]);

        // assert
        $this->userWasUpdated($user);
    }

    /**
     * The result holds both rows, and only the inserted one is marked as recently created.
     *
     * The inserted models come first, so the updated row is the second one in the result.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testResult(): void
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
        /** @var \Tests\App\Collection\UserCollection $result */
        $result = $sut->upsertAndReturn($users);

        // assert
        self::assertInstanceOf(UserCollection::class, $result);
        self::assertCount($users->count(), $result);
        self::assertEquals($users->get(0)->id, $result->get(1)->id);
        self::assertCount(1, $result->where('wasRecentlyCreated', true));

        $this->returnedUserWasUpserted($users->get(0), $result->get(1));
        $this->returnedUserWasUpserted($users->get(1), $result->get(0));
    }

    /**
     * A batch of unknown rows comes back as models which are marked as recently created.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testResultCreateOnly(): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->makeOne(),
        ]);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        /** @var \Tests\App\Collection\UserCollection $result */
        $result = $sut->upsertAndReturn($users);

        // assert
        self::assertInstanceOf(UserCollection::class, $result);
        self::assertCount($users->count(), $result);
        self::assertCount(1, $result->where('wasRecentlyCreated', true));

        $this->returnedUserWasUpserted($users->get(0), $result->get(0));
    }

    /**
     * A batch of known rows comes back with the same keys and the new attributes.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testResultUpdateOnly(): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
        ]);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        /** @var \Tests\App\Collection\UserCollection $result */
        $result = $sut->upsertAndReturn($users);

        // assert
        self::assertInstanceOf(UserCollection::class, $result);
        self::assertCount($users->count(), $result);
        self::assertEquals($users->get(0)->id, $result->get(0)->id);

        $this->returnedUserWasUpserted($users->get(0), $result->get(0));
    }
}
