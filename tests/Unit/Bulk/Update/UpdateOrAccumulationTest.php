<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Illuminate\Support\Facades\DB;
use JsonException;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

/**
 * @internal
 */
class UpdateOrAccumulationTest extends TestCase
{
    use UpdateTestTrait;

    public function testBigChunkSize(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $dbConnectionName = $users->get(0)->getConnectionName();
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);
        DB::connection($dbConnectionName)->enableQueryLog();

        // act
        $sut->updateOrAccumulate($users);

        // assert
        self::assertEmpty(
            DB::connection($dbConnectionName)->getQueryLog()
        );
        $users->each(
            fn (User $user) => $this->userWasNotUpdated($user)
        );
    }

    /**
     * @param string $uniqBy
     *
     * @return void
     *
     * @throws JsonException
     *
     * @dataProvider dataProvider
     */
    public function testSmallChunkSize(string $uniqBy): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy($uniqBy)
            ->chunk(2);

        // act
        $sut->updateOrAccumulate($users);

        // assert
        $users->each(
            fn (User $user) => $this->userWasUpdated($user)
        );
    }

    public function dataProvider(): array
    {
        return [
            'email' => ['email'],
            'id' => ['id'],
        ];
    }
}
