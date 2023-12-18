<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Upsert;

use Lapaliv\BulkUpsert\Contracts\BulkException;
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
final class UpsertOrAccumulateTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     *
     * @throws BulkException
     */
    public function testBigChunkSize(string $model): void
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
        $sut->upsertOrAccumulate($users);

        // assert
        $this->userWasNotUpdated($users->get(0));
        $this->userWasNotCreated($users->get(1));
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     *
     * @throws BulkException
     */
    public function testSmallChunkSize(string $model): void
    {
        // arrange
        $this->userGenerator->setModel($model);
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
        ]);
        $sut = $model::query()
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
     * @param class-string<User> $model
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     *
     * @throws BulkException
     */
    public function testSmallChunkSizeWithExtraCount(string $model): void
    {
        // arrange
        $this->userGenerator->setModel($model);
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
            $this->userGenerator->makeOne(),
        ]);
        $sut = $model::query()
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
     * @param class-string<User> $model
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     *
     * @throws BulkException
     */
    public function testSaveAccumulated(string $model): void
    {
        // arrange
        $this->userGenerator->setModel($model);
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
        ]);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->upsertOrAccumulate($users);

        // act
        $sut->saveAccumulated();

        // assert
        $this->userWasUpdated($users->get(0));
        $this->userWasCreated($users->get(1));
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
