<?php

namespace Tests\Unit\Scenarios\CreateScenario;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Tests\App\Models\Story;
use Tests\App\Models\User;
use Tests\Unit\BulkAccumulationEntityTestTrait;
use Tests\Unit\Scenarios\CreateScenarioTestCase;
use Tests\Unit\UserTestTrait;
use PDOException;

/**
 * @internal
 */
class InsertTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;

    /**
     * Verifying the successful creation of users.
     *
     * @return void
     */
    public function testSuccessfully(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $users = User::factory()->count(2)->make();
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        $this->userWasCreated($users->get(0));
        $this->userWasCreated($users->get(1));
    }

    /**
     * If any of the rows already exist in the database, the request should trigger a PDOException,
     * and none of the rows should be updated.
     *
     * @return void
     */
    public function testDuplicate(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $existingUser = User::factory()->create();
        $users = User::factory()->count(2)->make();
        $users->get(0)->email = $existingUser->email;
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        try {
            $this->handleCreateScenario($data, $eventDispatcher);
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
     * If any of the rows already exist in the database and the 'ignoring' flag is set to true,
     * the request should not trigger a PDOException, and new rows should be inserted.
     *
     * @return void
     */
    public function testDuplicateWithIgnoring(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $existingUser = User::factory()->create();
        $users = User::factory()->count(2)->make();
        $users->get(0)->email = $existingUser->email;
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, ignore: true);

        // assert
        $this->userWasNotUpdated($users->get(0));
        $this->userExists($existingUser);
        $this->userWasCreated($users->get(1));
    }

    /**
     * After creation, the 'wasRecentlyCreated' flag must be set to true
     * if the model has the 'incrementing' flag set to true.
     *
     * @return void
     */
    public function testFlagWasRecentlyCreatedWithIncrementing(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $eventDispatcher->listen(BulkEventEnum::SAVED, function (User $user) {
            self::assertTrue($user->wasRecentlyCreated);
        });
        $users = User::factory()->count(2)->make();
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, ignore: true);
    }

    /**
     * After creation, the 'wasRecentlyCreated' flag must be set to true
     * if the model has the 'incrementing' flag set to false.
     *
     * @return void
     */
    public function testFlagWasRecentlyCreatedWithoutIncrementing(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(Story::class);
        $eventDispatcher->listen(BulkEventEnum::SAVED, function (Story $story) {
            self::assertTrue($story->wasRecentlyCreated);
        });
        $stories = Story::factory()->count(2)->make();
        $data = $this->getBulkAccumulationEntityFromCollection($stories, ['uuid']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, ignore: true);
    }
}
