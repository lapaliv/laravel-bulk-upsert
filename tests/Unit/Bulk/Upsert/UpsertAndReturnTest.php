<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Upsert;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCaseWrapper;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpsertAndReturnTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @return void
     *
     * @throws BulkException
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
     * @return void
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
     * @return void
     * @throws BulkException
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
        /** @var UserCollection $result */
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
     * @return void
     * @throws BulkException
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
        /** @var UserCollection $result */
        $result = $sut->upsertAndReturn($users);

        // assert
        self::assertInstanceOf(UserCollection::class, $result);
        self::assertCount($users->count(), $result);
        self::assertCount(1, $result->where('wasRecentlyCreated', true));

        $this->returnedUserWasUpserted($users->get(0), $result->get(0));
    }

    /**
     * @return void
     * @throws BulkException
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
        /** @var UserCollection $result */
        $result = $sut->upsertAndReturn($users);

        // assert
        self::assertInstanceOf(UserCollection::class, $result);
        self::assertCount($users->count(), $result);
        self::assertEquals($users->get(0)->id, $result->get(0)->id);

        $this->returnedUserWasUpserted($users->get(0), $result->get(0));
    }
}
