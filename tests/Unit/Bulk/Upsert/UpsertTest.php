<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Upsert;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpsertTest extends TestCase
{
    use UserTestTrait;

    public function testBase(): void
    {
        // arrange
        $users = new UserCollection([
            $this->userGenerator->createOneAndDirty(),
            $this->userGenerator->makeOne(),
        ]);
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->upsert($users);

        // assert
        $this->userWasUpdated($users->get(0));
        $this->userWasCreated($users->get(1));
    }

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
        $sut = MySqlUser::query()
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
