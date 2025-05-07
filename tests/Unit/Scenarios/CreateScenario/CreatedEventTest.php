<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenario;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\Unit\BulkAccumulationEntityTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\ModelListenerTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenarioTestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class CreatedEventTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * If the model has a listener for the 'created' event, then this listener should be called.
     *
     * @return void
     */
    public function testTriggering(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::CREATED, $eventDispatcher);
        $users = User::factory()->count(2)->make();
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        self::spyShouldHaveReceived($listener)->times($users->count());
    }

    /**
     * The listener for the 'created' event should receive only one argument, which must be the model.
     *
     * @return void
     */
    public function testListenerArguments(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::CREATED, $eventDispatcher);
        $users = User::factory()->count(2)->make();
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertModelListenerArguments($users, ...func_get_args())
            );
    }
}
