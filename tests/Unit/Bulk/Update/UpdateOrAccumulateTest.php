<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpdateOrAccumulateTest extends TestCase
{
    use UserTestTrait;

    public function testBigChunkSize(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = MySqlUser::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->updateOrAccumulate($users);

        // assert
        $users->each(
            fn (User $user) => $this->userWasNotUpdated($user)
        );
    }

    /**
     * @param string $uniqBy
     *
     * @return void
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
