<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpdateOrAccumulateTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testBigChunkSize(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->updateOrAccumulate($users);

        // assert
        $users->each(
            fn (User $user) => $this->userWasNotUpdated($user)
        );
    }

    /**
     * @param class-string<User> $model
     * @param string $uniqBy
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider dataProvider
     */
    public function testSmallChunkSize(string $model, string $uniqBy): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy($uniqBy)
            ->chunk(2);

        // act
        $sut->updateOrAccumulate($users);

        // assert
        $users->each(
            fn (User $user) => $this->userWasUpdated($user)
        );
    }

    /**
     * @param class-string<User> $model
     * @param string $uniqBy
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider dataProvider
     */
    public function testSmallChunkSizeWithExtraCount(string $model, string $uniqBy): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(5);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy($uniqBy)
            ->chunk($users->count() - 1);

        // act
        $sut->updateOrAccumulate($users);

        // assert
        $users->slice(0, $users->count() - 1)->each(
            fn (User $user) => $this->userWasUpdated($user)
        );
        $this->userWasNotUpdated($users->last());
    }

    /**
     * @param class-string<User> $model
     * @param string $uniqBy
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider dataProvider
     */
    public function testSaveAccumulated(string $model, string $uniqBy): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy($uniqBy)
            ->updateOrAccumulate($users);

        // act
        $sut->saveAccumulated();

        // assert
        $users->each(
            fn (User $user) => $this->userWasUpdated($user)
        );
    }

    public function dataProvider(): array
    {
        $target = [
            'email' => ['email'],
            'id' => ['id'],
        ];

        $result = [];

        foreach ($this->userModelsDataProvider() as $type => $model) {
            foreach ($target as $key => $value) {
                $result[$key . ' && ' . $type] = [
                    $model[0],
                    ...$value,
                ];
            }
        }

        return $result;
    }

    public function userModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class],
            'postgre' => [PostgreSqlUser::class],
        ];
    }
}
