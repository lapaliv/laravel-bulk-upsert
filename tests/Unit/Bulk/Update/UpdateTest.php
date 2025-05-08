<?php

namespace Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpdateTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @param array|callable|string $uniqBy
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider dataProvider
     */
    public function testBase(array|string|callable $uniqBy): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
            ->bulk()
            ->uniqueBy($uniqBy);

        // act
        $sut->update($users);

        // assert
        $users->each(
            fn(User $user) => $this->userWasUpdated($user)
        );
    }

    /**
     * @param array|callable|string $uniqBy
     *
     * @return void
     *
     * @throws BulkException
     * @throws RandomException
     * @dataProvider dataProvider
     */
    public function testWithTimestamps(array|string|callable $uniqBy): void
    {
        // arrange
        $expectedCreatedAt = Carbon::now()->subSeconds(
            random_int(100, 100_000)
        );
        $expectedUpdatedAt = Carbon::now()->subSeconds(
            random_int(100, 100_000)
        );
        $users = $this->userGenerator
            ->createCollectionAndDirty(2)
            ->each(
                function (User $user) use ($expectedCreatedAt, $expectedUpdatedAt): void {
                    $user->setCreatedAt($expectedCreatedAt);
                    $user->setUpdatedAt($expectedUpdatedAt);
                }
            );
        $sut = User::query()
            ->bulk()
            ->uniqueBy($uniqBy);

        // act
        $sut->update($users);

        // assert
        $users->each(
            function (User $user) use ($expectedCreatedAt, $expectedUpdatedAt): void {
                $this->assertDatabaseHas($user->getTable(), [
                    'id' => $user->id,
                    'created_at' => $expectedCreatedAt->toDateTimeString(),
                    'updated_at' => $expectedUpdatedAt->toDateTimeString(),
                ], $user->getConnectionName());
            }
        );
    }

    /**
     * If the Bulk gets models for updating, then their origins should be synced
     * even if the bulk does not have any listeners ending with "ed".
     *
     * @return void
     *
     * @throws BulkException
     */
    public function testIsDirtyAfterUpdating()
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()->bulk();

        // act
        $sut->update($users);

        // assert
        foreach ($users as $user) {
            self::assertFalse($user->isDirty());
            self::assertNotEmpty($user->getChanges());
        }
    }

    public function dataProvider(): array
    {
        return [
            'email' => ['email'],
            '[email]' => [['email']],
            '[[email]]' => [[['email']]],
            '() => email' => [fn() => 'email'],
            'id' => ['id'],
            '[id]' => [['id']],
            '[[id]]' => [['id']],
            '() => id' => [fn() => 'id'],
        ];
    }
}
