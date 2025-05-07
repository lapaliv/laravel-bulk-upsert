<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Create;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Exceptions\BulkIdentifierDidNotFind;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Observers\Observer;
use Lapaliv\BulkUpsert\Tests\TestCaseWrapper;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
final class CreateOrAccumulateTest extends TestCaseWrapper
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
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
