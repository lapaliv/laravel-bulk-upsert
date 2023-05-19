<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use JsonException;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
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

    public function userModels(): array
    {
        return [
            'mysql' => MySqlUser::class,
            'postgre' => PostgreSqlUser::class,
        ];
    }
}
