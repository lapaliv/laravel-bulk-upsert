<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Upsert;

use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
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

    public function userModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class],
            'postgre' => [PostgreSqlUser::class],
        ];
    }
}
