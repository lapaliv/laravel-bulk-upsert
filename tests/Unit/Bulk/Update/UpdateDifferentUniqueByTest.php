<?php

namespace Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * Updating rows when the builder knows more than one set of unique attributes.
 *
 * @internal
 */
final class UpdateDifferentUniqueByTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * Each row is matched by the unique attribute set which it really has, and is updated
     * instead of being inserted again.
     *
     * @param array|string $uniqueBy
     * @param array|string $orUniqueBy
     *
     * @return void
     *
     * @throws \JsonException
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('dataProvider')]
    public function test(string|array $uniqueBy, string|array $orUniqueBy): void
    {
        // arrange
        $userWithEmail = Arr::except(
            $this->userGenerator->createOneAndDirty()->toArray(),
            ['id']
        );
        $userWithId = Arr::except(
            $this->userGenerator->createOneAndDirty()->toArray(),
            ['email']
        );
        $connectionName = $this->userGenerator->makeOne()->getConnectionName();
        $sut = User::query()
            ->bulk()
            ->uniqueBy($uniqueBy)
            ->orUniqueBy($orUniqueBy);

        // act
        $sut->update([$userWithEmail, $userWithId]);

        // assert
        $this->assertDatabaseHas(User::table(), [
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

        $this->assertDatabaseMissing(User::table(), [
            'id' => $userWithId['id'],
            'created_at' => Carbon::now()->toDateTimeString(),
        ], $connectionName);

        $this->assertDatabaseHas(User::table(), [
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

        $this->assertDatabaseMissing(User::table(), [
            'email' => $userWithEmail['email'],
            'created_at' => Carbon::now()->toDateTimeString(),
        ], $connectionName);
    }

    /**
     * The unique attributes, written as a string and as arrays of different depth.
     *
     * @return array<string, array{array|string, array|string}>
     */
    public static function dataProvider(): array
    {
        return [
            'email, id' => ['email', 'id'],
            '[email], [id]' => [['email'], ['id']],
            '[[email]], [[id]]' => [[['email']], [['id']]],
        ];
    }
}
