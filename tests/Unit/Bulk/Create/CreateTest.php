<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Create;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Exceptions\BulkIdentifierDidNotFind;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Observers\Observer;
use Lapaliv\BulkUpsert\Tests\TestCaseWrapper;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class CreateTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @return void
     *
     * @throws BulkException
     */
    public function testBase(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        $sut = User::query()
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
                'birthday' => $user->birthday?->toDateString(),
                'phones' => $user->phones,
                'last_visited_at' => $user->last_visited_at?->toDateTimeString(),
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
                'deleted_at' => $user->deleted_at?->toDateTimeString(),
            ], $user->getConnectionName());
        }
    }

    /**
     * @return void
     *
     * @throws BulkException
     * @throws RandomException
     */
    public function testWithTimestamps(): void
    {
        // arrange
        $expectedCreatedAt = Carbon::now()->subSeconds(
            random_int(100, 100_000)
        );
        $expectedUpdatedAt = Carbon::now()->subSeconds(
            random_int(100, 100_000)
        );
        $users = $this->userGenerator
            ->makeCollection(2)
            ->each(
                function (User $user) use ($expectedCreatedAt, $expectedUpdatedAt): void {
                    $user->setCreatedAt($expectedCreatedAt);
                    $user->setUpdatedAt($expectedUpdatedAt);
                }
            );
        $sut = User::query()
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

    /**
     * @return void
     *
     * @throws BulkException
     */
    public function testStdRow(): void
    {
        // arrange
        $user = $this->userGenerator->makeOne();
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->create([(object)$user->toArray()]);

        // assert
        $this->userWasCreated($user);
    }

    /**
     * @return void
     *
     * @throws BulkException
     */
    public function testObjectRowWithMethodToArray(): void
    {
        // arrange
        $user = $this->userGenerator->makeOne();
        $userAsArray = $user->toArray();
        $userAsArray['gender'] = $user->gender->value;
        $className = 'SomeClass' . Str::random();
        eval("
            class {$className} {
                public function toArray(): array {
                    return json_decode('" . json_encode($userAsArray) . "', true);
                }
            }
        ");
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->create([new $className()]);

        // assert
        $this->userWasCreated($user);
    }

    /**
     * @return void
     *
     * @throws BulkException
     */
    public function testCreatingWithoutUniqueAttributesWithEvents(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $sut = User::query()->bulk();

        // assert
        $this->expectException(BulkIdentifierDidNotFind::class);

        // act
        $sut->create($users);
    }

    /**
     * @return void
     *
     * @throws BulkException
     */
    public function testCreatingWithoutUniqueAttributesWithoutEvents(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        $sut = User::query()->bulk();

        // act
        $sut->create($users);

        // assert
        $users->each(
            fn(User $user) => $this->userWasCreated($user)
        );
    }
}
