<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

/**
 * @internal
 */
class UpdateDifferentUniqueByTest extends TestCase
{
    use UpdateTestTrait;

    public function test(): void
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
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy('email')
            ->orUniqueBy('id');

        // act
        $sut->update([$userWithEmail, $userWithId]);

        // assert
        $this->assertDatabaseHas(MySqlUser::table(), [
            'id' => $userWithId['id'],
            'name' => $userWithId['name'],
            'gender' => $userWithId['gender']->value,
            'avatar' => $userWithId['avatar'],
            'posts_count' => $userWithId['posts_count'],
            'is_admin' => $userWithId['is_admin'],
            'balance' => $userWithId['balance'],
            'birthday' => $userWithId['birthday'],
            'phones' => DB::connection($connectionName)->raw(
                sprintf("cast('%s' as json)", json_encode($userWithId['phones'], JSON_THROW_ON_ERROR))
            ),
            'last_visited_at' => $userWithId['last_visited_at'],
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => $userWithId['deleted_at'],
        ], 'mysql');

        $this->assertDatabaseMissing(MySqlUser::table(), [
            'id' => $userWithId['id'],
            'created_at' => Carbon::now()->toDateTimeString(),
        ], 'mysql');

        $this->assertDatabaseHas(MySqlUser::table(), [
            'email' => $userWithEmail['email'],
            'name' => $userWithEmail['name'],
            'gender' => $userWithEmail['gender']->value,
            'avatar' => $userWithEmail['avatar'],
            'posts_count' => $userWithEmail['posts_count'],
            'is_admin' => $userWithEmail['is_admin'],
            'balance' => $userWithEmail['balance'],
            'birthday' => $userWithEmail['birthday'],
            'phones' => DB::connection($connectionName)->raw(
                sprintf("cast('%s' as json)", json_encode($userWithEmail['phones'], JSON_THROW_ON_ERROR))
            ),
            'last_visited_at' => $userWithEmail['last_visited_at'],
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => $userWithEmail['deleted_at'],
        ], 'mysql');

        $this->assertDatabaseMissing(MySqlUser::table(), [
            'email' => $userWithEmail['email'],
            'created_at' => Carbon::now()->toDateTimeString(),
        ], 'mysql');
    }
}
