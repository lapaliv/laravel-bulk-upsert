<?php

namespace Tests\Unit\Bulk\Create;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Exceptions\BulkIdentifierDidNotFind;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * The moment when `createOrAccumulate()` flushes the accumulated rows to the table.
 *
 * @internal
 */
final class CreateOrAccumulateTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * Nothing is inserted while the number of the rows stays below the chunk size.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testBigChunkSize(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk(100);

        // act
        $sut->createOrAccumulate($users);

        // assert
        foreach ($users as $user) {
            $this->assertDatabaseMissing($user->getTable(), [
                'email' => $user->email,
            ], $user->getConnectionName());
        }
    }

    /**
     * The rows are inserted as soon as they reach the chunk size.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSmallChunkSize(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk($users->count());

        // act
        $sut->createOrAccumulate($users);

        // assert
        foreach ($users as $user) {
            $this->userWasCreated($user);
        }
    }

    /**
     * Only a full chunk is inserted, and the rest of the rows stays in the accumulation.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSmallChunkSizeWithExtraCount(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(5);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk($users->count() - 1);

        // act
        $sut->createOrAccumulate($users);

        // assert
        foreach ($users->slice(0, $users->count() - 1) as $user) {
            $this->userWasCreated($user);
        }
        $this->userWasNotCreated($users->last());
    }

    /**
     * `saveAccumulated()` inserts the rows which are still waiting in the accumulation.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSaveAccumulation(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->createOrAccumulate($users);

        // act
        $sut->saveAccumulated();

        // assert
        $users->each(
            fn(User $user) => $this->userWasCreated($user)
        );
    }

    /**
     * Accumulating without unique attributes throws when the model has an observer.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
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
        $sut->createOrAccumulate($users);
    }

    /**
     * Without unique attributes and without events the rows are still inserted.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCreatingWithoutUniqueAttributesWithoutEvents(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        $sut = User::query()
            ->bulk()
            ->chunk(2);

        // act
        $sut->createOrAccumulate($users);

        // assert
        $users->each(
            fn(User $user) => $this->userWasCreated($user)
        );
    }
}
