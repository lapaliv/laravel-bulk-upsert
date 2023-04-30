<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Upsert;

use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpsertAndReturnTest extends TestCase
{
    use UserTestTrait;

    public function testDatabase(): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
        ]);
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->upsertAndReturn($users);

        // assert
        $this->userWasUpdated($users->get(0));
        $this->userWasCreated($users->get(1));
    }

    public function testDatabaseCreateOnly(): void
    {
        // arrange
        $user = $this->userGenerator->makeOne();
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->upsertAndReturn([$user]);

        // assert
        $this->userWasCreated($user);
    }

    public function testDatabaseUpdateOnly(): void
    {
        // arrange
        $user = $this->userGenerator->createOneAndDirty();
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->upsertAndReturn([$user]);

        // assert
        $this->userWasUpdated($user);
    }

    public function testResult(): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
        ]);
        $sut = MySqlUser::query()
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

    public function testResultCreateOnly(): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->makeOne(),
        ]);
        $sut = MySqlUser::query()
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

    public function testResultUpdateOnly(): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
        ]);
        $sut = MySqlUser::query()
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
