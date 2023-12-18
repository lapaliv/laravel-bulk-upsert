<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Upsert;

use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpsertAndReturnTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function testDatabase(string $model): void
    {
        // arrange
        $this->userGenerator->setModel($model);
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
        ]);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->upsertAndReturn($users);

        // assert
        $this->userWasUpdated($users->get(0));
        $this->userWasCreated($users->get(1));
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function testDatabaseCreateOnly(string $model): void
    {
        // arrange
        $user = $this->userGenerator
            ->setModel($model)
            ->makeOne();
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->upsertAndReturn([$user]);

        // assert
        $this->userWasCreated($user);
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function testDatabaseUpdateOnly(string $model): void
    {
        // arrange
        $user = $this->userGenerator
            ->setModel($model)
            ->createOneAndDirty();
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->upsertAndReturn([$user]);

        // assert
        $this->userWasUpdated($user);
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function testResult(string $model): void
    {
        // arrange
        $this->userGenerator->setModel($model);
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
        ]);
        $sut = $model::query()
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
     * @param class-string<User> $model
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function testResultCreateOnly(string $model): void
    {
        // arrange
        $this->userGenerator->setModel($model);
        $users = new UserCollection([
            $this->userGenerator->makeOne(),
        ]);
        $sut = $model::query()
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
     * @param class-string<User> $model
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function testResultUpdateOnly(string $model): void
    {
        // arrange
        $this->userGenerator->setModel($model);
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
        ]);
        $sut = $model::query()
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

    public function userModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class],
            'pgsql' => [PostgreSqlUser::class],
            'sqlite' => [SqLiteUser::class],
        ];
    }
}
