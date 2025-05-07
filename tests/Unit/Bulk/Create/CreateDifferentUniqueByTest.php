<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Create;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class CreateDifferentUniqueByTest extends TestCase
{
    use UserTestTrait;

    /**
     * @return void
     *
     * @throws BulkException
     */
    public function test(): void
    {
        // arrange
        $userWithEmail = Arr::except(
            $this->userGenerator->makeOne()->toArray(),
            ['avatar']
        );
        $userWithAvatar = Arr::except(
            $this->userGenerator->makeOne()->toArray(),
            ['email']
        );
        $connectionName = $this->userGenerator
            ->makeOne()
            ->getConnectionName();
        $sut = User::query()
            ->bulk()
            ->uniqueBy('email')
            ->orUniqueBy('avatar');

        // act
        $sut->create([$userWithEmail, $userWithAvatar]);

        // assert
        $this->assertDatabaseHas(User::table(), [
            'name' => $userWithAvatar['name'],
            'gender' => $userWithAvatar['gender']->value,
            'avatar' => $userWithAvatar['avatar'],
            'posts_count' => $userWithAvatar['posts_count'],
            'is_admin' => $userWithAvatar['is_admin'],
            'balance' => $userWithAvatar['balance'],
            'birthday' => $userWithAvatar['birthday'],
            'phones' => $userWithAvatar['phones'],
            'last_visited_at' => $userWithAvatar['last_visited_at'],
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => null,
        ], $connectionName);

        $this->assertDatabaseHas(User::table(), [
            'email' => $userWithEmail['email'],
            'name' => $userWithEmail['name'],
            'gender' => $userWithEmail['gender']->value,
            'posts_count' => $userWithEmail['posts_count'],
            'is_admin' => $userWithEmail['is_admin'],
            'balance' => $userWithEmail['balance'],
            'birthday' => $userWithEmail['birthday'],
            'phones' => $userWithEmail['phones'],
            'last_visited_at' => $userWithEmail['last_visited_at'],
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => null,
        ], $connectionName);
    }
}
