<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Create;

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
final class CreateTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws JsonException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testBase(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->makeCollection(2);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->create($users);

        // assert
        foreach ($users as $user) {
            $this->assertDatabaseHas($user->getTable(), [
                'email' => $user->email,
                'name' => $user->name,
                'gender' => $user->gender->value,
                'avatar' => $user->avatar,
                'posts_count' => $user->posts_count,
                'is_admin' => $user->is_admin,
                'balance' => $user->balance,
                'birthday' => $user->birthday,
                'phones' => $user->phones,
                'last_visited_at' => $user->last_visited_at,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
                'deleted_at' => $user->deleted_at?->toDateTimeString(),
            ], $user->getConnectionName());
        }
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws JsonException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testWithTimestamps(string $model): void
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
            ->makeCollection(2)
            ->each(
                function (User $user) use ($expectedCreatedAt, $expectedUpdatedAt): void {
                    $user->setCreatedAt($expectedCreatedAt);
                    $user->setUpdatedAt($expectedUpdatedAt);
                }
            );
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->create($users);

        // assert
        /** @var User $user */
        foreach ($users as $user) {
            $this->assertDatabaseHas($user->getTable(), [
                'email' => $user->email,
                'created_at' => $expectedCreatedAt->toDateTimeString(),
                'updated_at' => $expectedUpdatedAt->toDateTimeString(),
            ], $user->getConnectionName());
        }
    }

    public function userModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class],
            'postgre' => [PostgreSqlUser::class],
        ];
    }
}
