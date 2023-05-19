<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use JsonException;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpdateDifferentUniqueByTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     * @param array|string $uniqueBy
     * @param array|string $orUniqueBy
     *
     * @return void
     *
     * @throws JsonException
     *
     * @dataProvider dataProvider
     */
    public function test(string $model, string|array $uniqueBy, string|array $orUniqueBy): void
    {
        // arrange
        $this->userGenerator->setModel($model);
        $userWithEmail = Arr::except(
            $this->userGenerator->createOneAndDirty()->toArray(),
            ['id']
        );
        $userWithId = Arr::except(
            $this->userGenerator->createOneAndDirty()->toArray(),
            ['email']
        );
        $connectionName = $this->userGenerator->makeOne()->getConnectionName();
        $sut = $model::query()
            ->bulk()
            ->uniqueBy($uniqueBy)
            ->orUniqueBy($orUniqueBy);

        // act
        $sut->update([$userWithEmail, $userWithId]);

        // assert
        $this->assertDatabaseHas($model::table(), [
            'id' => $userWithId['id'],
            'name' => $userWithId['name'],
            'gender' => $userWithId['gender']->value,
            'avatar' => $userWithId['avatar'],
            'posts_count' => $userWithId['posts_count'],
            'is_admin' => $userWithId['is_admin'],
            'balance' => $userWithId['balance'],
            'birthday' => $userWithId['birthday'],
            'phones' => $userWithId['phones'],
            'last_visited_at' => $userWithId['last_visited_at'],
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => $userWithId['deleted_at'],
        ], $connectionName);

        $this->assertDatabaseMissing($model::table(), [
            'id' => $userWithId['id'],
            'created_at' => Carbon::now()->toDateTimeString(),
        ], $connectionName);

        $this->assertDatabaseHas($model::table(), [
            'email' => $userWithEmail['email'],
            'name' => $userWithEmail['name'],
            'gender' => $userWithEmail['gender']->value,
            'avatar' => $userWithEmail['avatar'],
            'posts_count' => $userWithEmail['posts_count'],
            'is_admin' => $userWithEmail['is_admin'],
            'balance' => $userWithEmail['balance'],
            'birthday' => $userWithEmail['birthday'],
            'phones' => $userWithEmail['phones'],
            'last_visited_at' => $userWithEmail['last_visited_at'],
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => $userWithEmail['deleted_at'],
        ], $connectionName);

        $this->assertDatabaseMissing($model::table(), [
            'email' => $userWithEmail['email'],
            'created_at' => Carbon::now()->toDateTimeString(),
        ], $connectionName);
    }

    public function dataProvider(): array
    {
        $target = [
            'email, id' => ['email', 'id'],
            '[email], [id]' => [['email'], ['id']],
            '[[email]], [[id]]' => [[['email']], [['id']]],
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
