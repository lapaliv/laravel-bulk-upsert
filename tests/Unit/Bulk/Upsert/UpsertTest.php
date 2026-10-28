<?php

namespace Tests\Unit\Bulk\Upsert;

use Carbon\Carbon;
use Tests\App\Collection\UserCollection;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * The rows which `upsert()` inserts and updates in the same call.
 *
 * @internal
 */
final class UpsertTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * A known row is updated and an unknown row is inserted in the same call.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testBase(): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
        ]);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->upsert($users);

        // assert
        $this->userWasUpdated($users->get(0));
        $this->userWasCreated($users->get(1));
    }

    /**
     * The timestamps which the models already carry are kept instead of the current time.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     * @throws \Random\RandomException
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
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
        ]);
        $users->each(
            function (User $user) use ($expectedCreatedAt, $expectedUpdatedAt): void {
                $user->setCreatedAt($expectedCreatedAt);
                $user->setUpdatedAt($expectedUpdatedAt);
            }
        );
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->upsert($users);

        // assert
        $users->each(
            function (User $user) use ($expectedCreatedAt, $expectedUpdatedAt): void {
                $this->assertDatabaseHas($user->getTable(), [
                    'email' => $user->email,
                    'created_at' => $expectedCreatedAt->toDateTimeString(),
                    'updated_at' => $expectedUpdatedAt->toDateTimeString(),
                ], $user->getConnectionName());
            }
        );
    }
}
