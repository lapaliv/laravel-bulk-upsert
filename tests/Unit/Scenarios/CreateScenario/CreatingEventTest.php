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
class CreatingEventTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * If the model has a listener for the 'creating' event, then this listener should be called.
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
        $listener = $this->makeSimpleModelListener(BulkEventEnum::CREATING, $eventDispatcher);
        $users = $this->makeUserCollection($userModel, 2);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        $this->spyShouldHaveReceived($listener)->times($users->count());
    }

    /**
     * If the previous listener returns false, then the listener for the 'creating' event must not be called.
     *
     * @param string $previousEventName
     *
     * @return void
     *
     * @dataProvider notTriggeringWhenPreviousListenerReturnedFalseDataProvider
     */
    public function testNotTriggeringWhenSavingReturnedFalse(string $previousEventName): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(MySqlUser::class);
        $this->makeModelListenerWithReturningValue(
            $previousEventName,
            $eventDispatcher,
            [false, false]
        );
        $creatingListener = $this->makeSimpleModelListener(BulkEventEnum::CREATING, $eventDispatcher);
        $users = $this->makeUserCollection(MySqlUser::class, 2);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        $this->spyShouldNotHaveReceived($creatingListener);
    }

    /**
     * The listener for the 'creating' event should receive only one argument, which must be the model.
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
        $listener = $this->makeSimpleModelListener(BulkEventEnum::CREATING, $eventDispatcher);
        $users = $this->makeUserCollection($userModel, 2);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        $this->spyShouldHaveReceived($listener)
            ->withArgs(
                fn () => $this->assertModelListenerArguments($users, ...func_get_args())
            );
    }

    /**
     * The data for the test 'testNotTriggeringWhenSavingReturnedFalse'.
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
