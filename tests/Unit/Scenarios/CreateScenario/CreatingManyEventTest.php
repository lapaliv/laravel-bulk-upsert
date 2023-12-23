<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenario;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\Unit\BulkAccumulationEntityTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\ModelListenerTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenarioTestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class CreatingManyEventTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * If the model has a listener for the 'creatingMany' event, then this listener should be called.
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
        $listener = $this->makeSimpleModelListener(BulkEventEnum::CREATING_MANY, $eventDispatcher);
        $users = $this->makeUserCollection($userModel, 2);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        $this->spyShouldHaveReceived($listener)->once();
    }

    /**
     * If the previous listener returns false, then the listener for the 'creatingMany' event must not be called.
     *
     * @param string $previousEventName
     *
     * @return void
     *
     * @dataProvider notTriggeringWhenPreviousListenerReturnedFalseDataProvider
     */
    public function testNotTriggeringWhenPreviousListenerReturnedFalse(string $previousEventName): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(MySqlUser::class);
        $this->makeModelListenerWithReturningValue(
            $previousEventName,
            $eventDispatcher,
            [false, false]
        );
        $creatingManyListener = $this->makeSimpleModelListener(BulkEventEnum::CREATING_MANY, $eventDispatcher);
        $users = $this->makeUserCollection(MySqlUser::class, 2);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        $this->spyShouldNotHaveReceived($creatingManyListener);
    }

    /**
     * The listener for the 'creatingMany' event should receive two arguments:
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
        $listener = $this->makeSimpleModelListener(BulkEventEnum::CREATING_MANY, $eventDispatcher);
        $users = $this->makeUserCollection($userModel, 2);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        $this->spyShouldHaveReceived($listener)
            ->withArgs(
                fn () => $this->assertCollectionListenerArguments($users, ...func_get_args())
            );
    }

    /**
     * The data for the test 'testNotTriggeringWhenPreviousListenerReturnedFalse'.
     *
     * @return array[]
     */
    public function notTriggeringWhenPreviousListenerReturnedFalseDataProvider(): array
    {
        return [
            'saving' => [BulkEventEnum::SAVING],
            'savingMany' => [BulkEventEnum::SAVING_MANY],
        ];
    }
}
