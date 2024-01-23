<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use JsonException;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpdateTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     * @param array|callable|string $uniqBy
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider dataProvider
     */
    public function testBase(string $model, array|string|callable $uniqBy): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy($uniqBy);

        // act
        $sut->update($users);

        // assert
        $users->each(
            fn (User $user) => $this->userWasUpdated($user)
        );
    }

    /**
     * @param class-string<User> $model
     * @param array|callable|string $uniqBy
     *
     * @return void
     *
     * @throws JsonException
     * @throws BulkException
     *
     * @dataProvider dataProvider
     */
    public function testWithTimestamps(string $model, array|string|callable $uniqBy): void
    {
        // arrange
        $expectedCreatedAt = Carbon::now()->subSeconds(
            random_int(100, 100_000)
        );
        $expectedUpdatedAt = Carbon::now()->subSeconds(
            random_int(100, 100_000)
        );
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2)
            ->each(
                function (User $user) use ($expectedCreatedAt, $expectedUpdatedAt): void {
                    $user->setCreatedAt($expectedCreatedAt);
                    $user->setUpdatedAt($expectedUpdatedAt);
                }
            );
        $sut = $model::query()
            ->bulk()
            ->uniqueBy($uniqBy);

        // act
        $sut->update($users);

        // assert
        $users->each(
            function (User $user) use ($expectedCreatedAt, $expectedUpdatedAt): void {
                $this->assertDatabaseHas($user->getTable(), [
                    'id' => $user->id,
                    'created_at' => $expectedCreatedAt->toDateTimeString(),
                    'updated_at' => $expectedUpdatedAt->toDateTimeString(),
                ], $user->getConnectionName());
            }
        );
    }

    /**
     * If the Bulk gets models for updating, then their origins should be synced
     * even if the bulk does not have any listeners ending with "ed".
     *
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testIsDirtyAfterUpdating(string $model)
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()->bulk();

        // act
        $sut->update($users);

        // assert
        foreach ($users as $user) {
            self::assertFalse($user->isDirty());
            self::assertNotEmpty($user->getChanges());
        }
    }

    public function dataProvider(): array
    {
        $target = [
            'email' => ['email'],
            '[email]' => [['email']],
            '[[email]]' => [[['email']]],
            '() => email' => [fn () => 'email'],
            'id' => ['id'],
            '[id]' => [['id']],
            '[[id]]' => [['id']],
            '() => id' => [fn () => 'id'],
        ];

        $result = [];

        foreach ($this->userModels() as $type => $model) {
            foreach ($target as $key => $value) {
                $result[$key . ' && ' . $type] = [
                    $model,
                    ...$value,
                ];
            }
        }

        return $result;
    }

    public function userModelsDataProvider(): array
    {
        $result = [];

        foreach ($this->userModels() as $key => $value) {
            $result[$key] = [$value];
        }

        return $result;
    }

    public function userModels(): array
    {
        return [
            'mysql' => MySqlUser::class,
            'pgsql' => PostgreSqlUser::class,
            'sqlite' => SqLiteUser::class,
        ];
    }
}
