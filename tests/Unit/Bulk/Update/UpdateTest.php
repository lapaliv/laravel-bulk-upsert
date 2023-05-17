<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Exception;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpdateTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param array|callable|string $uniqBy
     *
     * @return void
     *
     * @dataProvider dataProvider
     */
    public function testBase(array|string|callable $uniqBy): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy($uniqBy);

        // act
        $sut->update($users);

        // assert
        $users->each(
            fn (User $user) => $this->userWasUpdated($user)
        );
    }

    /**
     * @param array|callable|string $uniqBy
     *
     * @return void
     *
     * @dataProvider dataProvider
     *
     * @throws Exception
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
        $sut = MySqlUser::query()
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

    public function dataProvider(): array
    {
        return [
            'email' => ['email'],
            '[email]' => [['email']],
            '[[email]]' => [[['email']]],
            '() => email' => [fn () => 'email'],
            'id' => ['id'],
            '[id]' => [['id']],
            '[[id]]' => [['id']],
            '() => id' => [fn () => 'id'],
        ];
    }
}
