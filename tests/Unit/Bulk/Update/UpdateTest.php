<?php

namespace Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * The rows which `update()` writes into the table.
 *
 * @internal
 */
final class UpdateTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * Every dirty model is written to the table, whichever shape the unique attribute has.
     *
     * @param array|callable|string $uniqBy
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('dataProvider')]
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
     * The timestamps which the model already carries are kept instead of the current time.
     *
     * @param array|callable|string $uniqBy
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     * @throws \Random\RandomException
     */
    #[DataProvider('dataProvider')]
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
     * The updated models are no longer dirty and keep their changes.
     *
     * The origins are synced even when the builder has no listener whose name ends with `ed`.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
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

    /**
     * The unique attributes, written as a string, as nested arrays and as a closure.
     *
     * @return array<string, array{array|callable|string}>
     */
    public static function dataProvider(): array
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
