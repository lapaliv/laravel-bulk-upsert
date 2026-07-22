<?php

namespace Tests\Unit\Scenarios\CreateScenario;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use PDOException;
use Tests\App\Models\Story;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * How the create operation writes rows to the database, verified through the public bulk API.
 *
 * @internal
 */
final class InsertTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * All the passed rows are inserted.
     *
     * @throws BulkException
     */
    public function testSuccessfully(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        $sut = User::query()->bulk()->uniqueBy(['email']);

        // act
        $sut->create($users);

        // assert
        $this->userWasCreated($users->get(0));
        $this->userWasCreated($users->get(1));
    }

    /**
     * If any row already exists, the whole insert fails with a PDOException
     * and no row from the batch is written (the transaction is rolled back).
     *
     * @throws BulkException
     */
    public function testDuplicate(): void
    {
        // arrange
        $existingUser = $this->userGenerator->createOne();
        $users = $this->userGenerator->makeCollection(2);
        $users->get(0)->email = $existingUser->email;
        $sut = User::query()->bulk()->uniqueBy(['email']);

        // act
        try {
            $sut->create($users);
        } catch (PDOException) {
            // assert
            $this->userWasNotUpdated($users->get(0));
            $this->userExists($existingUser);
            $this->userDoesNotExist($users->get(1));

            return;
        }

        self::fail('Failed asserting that exception of type "PDOException" is thrown.');
    }

    /**
     * With the "ignore conflicts" flag the existing row is untouched and the
     * remaining new rows are still inserted.
     *
     * @throws BulkException
     */
    public function testDuplicateWithIgnoring(): void
    {
        // arrange
        $existingUser = $this->userGenerator->createOne();
        $users = $this->userGenerator->makeCollection(2);
        $users->get(0)->email = $existingUser->email;
        $sut = User::query()->bulk()->uniqueBy(['email']);

        // act
        $sut->create($users, ignoreConflicts: true);

        // assert
        $this->userWasNotUpdated($users->get(0));
        $this->userExists($existingUser);
        $this->userWasCreated($users->get(1));
    }

    /**
     * A freshly inserted auto-incrementing model reports wasRecentlyCreated === true.
     *
     * @throws BulkException
     */
    public function testFlagWasRecentlyCreatedWithIncrementing(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        Observer::listen(BulkEventEnum::SAVED, function (User $user): void {
            self::assertTrue($user->wasRecentlyCreated);
        });

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users, ignoreConflicts: true);
    }

    /**
     * A freshly inserted model with a non-incrementing key reports wasRecentlyCreated === true.
     *
     * @throws BulkException
     */
    public function testFlagWasRecentlyCreatedWithoutIncrementing(): void
    {
        // arrange
        $stories = Story::factory()->count(2)->make();
        Story::observe(Observer::class);
        Observer::listen(BulkEventEnum::SAVED, function (Story $story): void {
            self::assertTrue($story->wasRecentlyCreated);
        });

        // act
        Story::query()->bulk()->uniqueBy(['uuid'])->create($stories, ignoreConflicts: true);
    }
}
