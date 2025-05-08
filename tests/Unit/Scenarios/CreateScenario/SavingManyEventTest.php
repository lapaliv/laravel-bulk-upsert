<?php

namespace Tests\Unit\Scenarios\CreateScenario;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Tests\App\Models\User;
use Tests\Unit\BulkAccumulationEntityTestTrait;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\Scenarios\CreateScenarioTestCase;
use Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class SavingManyEventTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * If the model has a listener for the 'savingMany' event, then this listener should be called.
     *
     * @return void
     */
    public function testTriggering(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::SAVING_MANY, $eventDispatcher);
        $users = User::factory()->count(2)->make();
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        self::spyShouldHaveReceived($listener)->once();
    }

    /**
     * The listener for the 'savingMany' event should receive two arguments:
     * the model and an object of the BulkRows class.
     *
     * @return void
     */
    public function testListenerArguments(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::SAVING_MANY, $eventDispatcher);
        $users = User::factory()->count(2)->make();
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertCollectionListenerArguments($users, ...func_get_args())
            );
    }
}
