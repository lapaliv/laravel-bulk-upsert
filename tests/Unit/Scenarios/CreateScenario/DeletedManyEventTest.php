<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenario;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Tests\Unit\BulkAccumulationEntityTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\ModelListenerTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenarioTestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class DeletedManyEventTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * If the model has a listener for the 'deletedMany' event, then this listener should be called.
     *
     * @param string $userModel
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function testTriggering(string $userModel): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher($userModel);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::DELETED_MANY, $eventDispatcher);
        $users = $this->makeUserCollection($userModel, 2, ['deleted_at' => Carbon::now()]);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, deletedAtColumn: 'deleted_at');

        // assert
        $this->spyShouldHaveReceived($listener)->once();
    }

    /**
     * The listener for the 'deletedMany' event should receive two arguments:
     * the model and an object of the BulkRows class.
     *
     * @param string $userModel
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function testListenerArguments(string $userModel): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher($userModel);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::DELETED_MANY, $eventDispatcher);
        $users = $this->makeUserCollection($userModel, 2, ['deleted_at' => Carbon::now()]);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, deletedAtColumn: 'deleted_at');

        // assert
        $this->spyShouldHaveReceived($listener)
            ->withArgs(
                fn () => $this->assertCollectionListenerArguments($users, ...func_get_args())
            );
    }
}
