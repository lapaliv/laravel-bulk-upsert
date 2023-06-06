<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Create;

use JsonException;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Exceptions\BulkIdentifierDidNotFind;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Observers\UserObserver;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class CreateOrAccumulateTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     * @throws JsonException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testBigChunkSize(string $model): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk(100);

        // act
        $sut->createOrAccumulate($users);

        // assert
        foreach ($users as $user) {
            $this->assertDatabaseMissing($user->getTable(), [
                'email' => $user->email,
            ], $user->getConnectionName());
        }
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testSmallChunkSize(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->makeCollection(2);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk($users->count());

        // act
        $sut->createOrAccumulate($users);

        // assert
        foreach ($users as $user) {
            $this->userWasCreated($user);
        }
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testSmallChunkSizeWithExtraCount(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->makeCollection(5);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk($users->count() - 1);

        // act
        $sut->createOrAccumulate($users);

        // assert
        foreach ($users->slice(0, $users->count() - 1) as $user) {
            $this->userWasCreated($user);
        }
        $this->userWasNotCreated($users->last());
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testSaveAccumulation(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->makeCollection(2);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->createOrAccumulate($users);

        // act
        $sut->saveAccumulated();

        // assert
        $users->each(
            fn (User $user) => $this->userWasCreated($user)
        );
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testCreatingWithoutUniqueAttributesWithEvents(string $model): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        $model::observe(UserObserver::class);
        $sut = $model::query()->bulk();

        // assert
        $this->expectException(BulkIdentifierDidNotFind::class);

        // act
        $sut->createOrAccumulate($users);
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testCreatingWithoutUniqueAttributesWithoutEvents(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->makeCollection(2);
        $sut = $model::query()
            ->bulk()
            ->chunk(2);

        // act
        $sut->createOrAccumulate($users);

        // assert
        $users->each(
            fn (User $user) => $this->userWasCreated($user)
        );
    }

    public function userModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class],
            'postgre' => [PostgreSqlUser::class],
        ];
    }
}
