<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenario;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\Unit\BulkAccumulationEntityTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\ModelListenerTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenarioTestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class SyncOriginalTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * After creation, the model should be clear, without any changes.
     *
     * @param string $userModel
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function test(string $userModel): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher($userModel);
        $users = $this->makeUserCollection($userModel, 2);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);
        $usersFromEvent = null;
        $eventDispatcher->listen(
            BulkEventEnum::SAVED_MANY,
            function (UserCollection $users) use (&$usersFromEvent) {
                $usersFromEvent = $users;
            }
        );

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        foreach ($usersFromEvent as $user) {
            self::assertFalse($user->isDirty());
            self::assertEmpty($user->getChanges());
        }
    }
}
