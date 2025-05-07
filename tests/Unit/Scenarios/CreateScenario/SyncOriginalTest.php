<?php

namespace Tests\Unit\Scenarios\CreateScenario;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Tests\App\Collection\UserCollection;
use Tests\App\Models\User;
use Tests\Unit\BulkAccumulationEntityTestTrait;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\Scenarios\CreateScenarioTestCase;
use Tests\Unit\UserTestTrait;

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
     * @return void
     */
    public function test(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $users = User::factory()->count(2)->make();
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
