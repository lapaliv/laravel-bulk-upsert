<?php

namespace Tests\Unit\Scenarios\CreateScenario;

use Carbon\Carbon;
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
class DeletedEventTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * If the model has a listener for the 'deleted' event, then this listener should be called.
     *
     * @return void
     */
    public function testTriggering(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::DELETED, $eventDispatcher);
        $users = User::factory()->count(2)->make(['deleted_at' => Carbon::now()]);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, deletedAtColumn: 'deleted_at');

        // assert
        self::spyShouldHaveReceived($listener)->times($users->count());
    }

    /**
     * The listener for the 'deleted' event should receive only one argument, which must be the model.
     *
     * @return void
     */
    public function testListenerArguments(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::DELETED, $eventDispatcher);
        $users = User::factory()->count(2)->make(['deleted_at' => Carbon::now()]);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, deletedAtColumn: 'deleted_at');

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertModelListenerArguments($users, ...func_get_args())
            );
    }
}
