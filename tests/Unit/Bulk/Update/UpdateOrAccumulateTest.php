<?php

namespace Tests\Unit\Bulk\Update;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * The moment when `updateOrAccumulate()` flushes the accumulated rows to the table.
 *
 * @internal
 */
final class UpdateOrAccumulateTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * Nothing is written while the number of the rows stays below the chunk size.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
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
     * The rows are updated as soon as they reach the chunk size.
     *
     * @param string $uniqBy
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('dataProvider')]
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
     * Only a full chunk is updated, and the rest of the rows stays in the accumulation.
     *
     * @param string $uniqBy
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('dataProvider')]
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
     * `saveAccumulated()` updates the rows which are still waiting in the accumulation.
     *
     * @param string $uniqBy
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('dataProvider')]
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

    /**
     * The attributes which can be used as the unique one.
     *
     * @return array<string, array{string}>
     */
    public static function dataProvider(): array
    {
        return [
            'email' => ['email'],
            'id' => ['id'],
        ];
    }
}
