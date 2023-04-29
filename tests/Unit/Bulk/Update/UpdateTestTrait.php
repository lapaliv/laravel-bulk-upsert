<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Tests\App\Features\UserGenerator;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Observers\UserObserver;

/**
 * @internal
 */
trait UpdateTestTrait
{
    private UserGenerator $userGenerator;

    public function setUp(): void
    {
        parent::setUp();

        $this->userGenerator = new UserGenerator();
        Carbon::setTestNow(Carbon::now());
        UserObserver::flush();
    }

    private function userWasUpdated(User $user): void
    {
        self::assertDatabaseHas($user->getTable(), [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'gender' => $user->gender->value,
            'avatar' => $user->avatar,
            'posts_count' => $user->posts_count,
            'is_admin' => $user->is_admin,
            'balance' => $user->balance,
            'birthday' => $user->birthday,
            'phones' => DB::connection($user->getConnectionName())->raw(
                sprintf("cast('%s' as json)", json_encode($user->phones, JSON_THROW_ON_ERROR))
            ),
            'last_visited_at' => $user->last_visited_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'deleted_at' => $user->deleted_at?->toDateTimeString(),
        ], $user->getConnectionName());
    }

    private function userWasNotUpdated(User $user): void
    {
        self::assertDatabaseMissing($user->getTable(), [
            'id' => $user->id,
            'name' => $user->name,
        ], $user->getConnectionName());
    }
}
