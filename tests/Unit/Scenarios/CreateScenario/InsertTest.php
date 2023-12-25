<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenario;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Tests\App\Models\Story;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\Unit\BulkAccumulationEntityTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenarioTestCase;
use Lapaliv\BulkUpsert\Tests\Unit\StoryTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;
use PDOException;

/**
 * @internal
 */
class InsertTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;
    use StoryTestTrait;

    /**
     * Verifying the successful creation of users.
     *
     * @param string $userModel
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function testSuccessfully(string $userModel): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher($userModel);
        $users = $this->makeUserCollection($userModel, 2);
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
     * @param string $userModel
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function testDuplicate(string $userModel): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher($userModel);
        $existingUser = $this->createUser($userModel);
        $users = $this->makeUserCollection($userModel, 2);
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
     * @param string $userModel
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function testDuplicateWithIgnoring(string $userModel): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher($userModel);
        $existingUser = $this->createUser($userModel);
        $users = $this->makeUserCollection($userModel, 2);
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
     * @param string $userModel
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function testFlagWasRecentlyCreatedWithIncrementing(string $userModel): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher($userModel);
        $eventDispatcher->listen(BulkEventEnum::SAVED, function (User $user) {
            self::assertTrue($user->wasRecentlyCreated);
        });
        $users = $this->makeUserCollection($userModel, 2);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, ignore: true);
    }

    /**
     * After creation, the 'wasRecentlyCreated' flag must be set to true
     * if the model has the 'incrementing' flag set to false.
     *
     * @param string $storyModel
     *
     * @psalm-param class-string<Story> $storyModel
     *
     * @return void
     *
     * @dataProvider storyModelsDataProvider
     */
    public function testFlagWasRecentlyCreatedWithoutIncrementing(string $storyModel): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher($storyModel);
        $eventDispatcher->listen(BulkEventEnum::SAVED, function (Story $story) {
            self::assertTrue($story->wasRecentlyCreated);
        });
        $stories = $this->makeStoryCollection($storyModel, 2);
        $data = $this->getBulkAccumulationEntityFromCollection($stories, ['uuid']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, ignore: true);
    }
}
