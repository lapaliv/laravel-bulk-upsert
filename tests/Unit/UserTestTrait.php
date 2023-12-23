<?php

namespace Lapaliv\BulkUpsert\Tests\Unit;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Features\UserGenerator;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Observers\Observer;

/**
 * @internal
 */
trait UserTestTrait
{
    protected UserGenerator $userGenerator;

    public function setUp(): void
    {
        parent::setUp();

        $this->userGenerator = new UserGenerator();
        Carbon::setTestNow(Carbon::now());
        Observer::flush();
    }

    /**
     * The models for checking.
     *
     * @return array[]
     */
    public function userModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class],
            'psql' => [PostgreSqlUser::class],
            'sqlite' => [SqLiteUser::class],
        ];
    }

    protected function userWasCreated(User $user): void
    {
        self::assertDatabaseHas($user->getTable(), [
            'email' => $user->email,
            'name' => $user->name,
            'gender' => $user->gender->value,
            'avatar' => $user->avatar,
            'posts_count' => $user->posts_count,
            'is_admin' => $user->is_admin,
            'balance' => $user->balance,
            'birthday' => $user->birthday?->toDateString(),
            'phones' => $user->phones,
            'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => $user->deleted_at?->toDateTimeString(),
        ], $user->getConnectionName());
    }

    protected function userExists(User $user): void
    {
        self::assertDatabaseHas($user->getTable(), [
            'email' => $user->email,
            'name' => $user->name,
            'gender' => $user->gender->value,
            'avatar' => $user->avatar,
            'posts_count' => $user->posts_count,
            'is_admin' => $user->is_admin,
            'balance' => $user->balance,
            'birthday' => $user->birthday?->toDateString(),
            'phones' => $user->phones,
            'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
            'created_at' => $user->created_at->toDateTimeString(),
            'updated_at' => $user->updated_at->toDateTimeString(),
            'deleted_at' => $user->deleted_at?->toDateTimeString(),
        ], $user->getConnectionName());
    }

    protected function userDoesNotExist(User $user): void
    {
        self::assertDatabaseMissing($user->getTable(), [
            'email' => $user->email,
        ], $user->getConnectionName());
    }

    protected function userWasSoftDeleted(User $user): void
    {
        self::assertDatabaseHas($user->getTable(), [
            'email' => $user->email,
            'name' => $user->name,
            'gender' => $user->gender->value,
            'avatar' => $user->avatar,
            'posts_count' => $user->posts_count,
            'is_admin' => $user->is_admin,
            'balance' => $user->balance,
            'birthday' => $user->birthday?->toDateString(),
            'phones' => $user->phones,
            'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
            'created_at' => $user->created_at->toDateTimeString(),
            'updated_at' => $user->updated_at->toDateTimeString(),
        ], $user->getConnectionName());

        self::assertDatabaseMissing($user->getTable(), [
            'email' => $user->email,
            'deleted_at' => null,
        ], $user->getConnectionName());
    }

    protected function userWasUpdated(User $user): void
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
            'birthday' => $user->birthday?->toDateString(),
            'phones' => $user->phones,
            'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
            'created_at' => $user->created_at->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
            'deleted_at' => $user->deleted_at?->toDateTimeString(),
        ], $user->getConnectionName());
    }

    protected function userWasNotUpdated(User $user): void
    {
        self::assertDatabaseMissing($user->getTable(), [
            'id' => $user->id,
            'name' => $user->name,
        ], $user->getConnectionName());
    }

    protected function userWasNotCreated(User $user): void
    {
        self::assertDatabaseMissing($user->getTable(), [
            'email' => $user->email,
        ], $user->getConnectionName());
    }

    protected function returnedUserWasUpserted(User $expect, User $actual): void
    {
        self::assertEquals($expect->email, $actual->email);
        self::assertEquals($expect->name, $actual->name);
        self::assertEquals($expect->gender, $actual->gender);
        self::assertEquals($expect->avatar, $actual->avatar);
        self::assertEquals($expect->posts_count, $actual->posts_count);
        self::assertEquals($expect->is_admin, $actual->is_admin);
        self::assertEquals($expect->balance, $actual->balance);
        self::assertEquals($expect->birthday, $actual->birthday);
        self::assertEquals($expect->phones, $actual->phones);
        self::assertEquals($expect->last_visited_at, $actual->last_visited_at);
        self::assertEquals($expect->created_at ?? Carbon::now()->startOfSecond(), $actual->created_at);
        self::assertEquals(Carbon::now()->startOfSecond(), $actual->updated_at);
        self::assertEquals($expect->deleted_at, $actual->deleted_at);
        self::assertTrue($actual->id > 0);
    }

    protected function makeUserCollection(string $model, int $count, array $data = []): UserCollection
    {
        return call_user_func([$model, 'factory'])->count($count)->make($data);
    }

    protected function createUserCollection(string $model, int $count, array $data = []): UserCollection
    {
        return call_user_func([$model, 'factory'])->count($count)->create($data);
    }

    protected function createDirtyUserCollection(string $model, int $count, array $data = []): UserCollection
    {
        $users = $this->createUserCollection($model, $count);
        $result = $this->makeUserCollection($model, $count, $data);

        foreach ($result as $key => $user) {
            $user->email = $users->get($key)->email;
        }

        return $result;
    }

    protected function createUser(string $model, array $data = []): User
    {
        return call_user_func([$model, 'factory'])->create($data);
    }
}
