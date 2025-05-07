<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class UpdateOrAccumulateTest extends TestCase
{
    use UserTestTrait;

    /**
     * @return void
     *
     * @throws BulkException
     */
    public function testBigChunkSize(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);

        // act
        $sut->updateOrAccumulate($users);

        // assert
        $users->each(
            fn(User $user) => $this->userWasNotUpdated($user)
        );
    }

    /**
     * @param string $uniqBy
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider dataProvider
     */
    public function testSmallChunkSize(string $uniqBy): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
            ->bulk()
            ->uniqueBy($uniqBy)
            ->chunk(2);

        // act
        $sut->updateOrAccumulate($users);

        // assert
        $users->each(
            fn(User $user) => $this->userWasUpdated($user)
        );
    }

    /**
     * @param string $uniqBy
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider dataProvider
     */
    public function testSmallChunkSizeWithExtraCount(string $uniqBy): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(5);
        $sut = User::query()
            ->bulk()
            ->uniqueBy($uniqBy)
            ->chunk($users->count() - 1);

        // act
        $sut->updateOrAccumulate($users);

        // assert
        $users->slice(0, $users->count() - 1)->each(
            fn(User $user) => $this->userWasUpdated($user)
        );
        $this->userWasNotUpdated($users->last());
    }

    /**
     * @param string $uniqBy
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider dataProvider
     */
    public function testSaveAccumulated(string $uniqBy): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
            ->bulk()
            ->uniqueBy($uniqBy)
            ->updateOrAccumulate($users);

        // act
        $sut->saveAccumulated();

        // assert
        $users->each(
            fn(User $user) => $this->userWasUpdated($user)
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
