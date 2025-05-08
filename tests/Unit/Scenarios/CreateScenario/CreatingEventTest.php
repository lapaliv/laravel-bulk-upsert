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
class CreatingEventTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * If the model has a listener for the 'creating' event, then this listener should be called.
     *
     * @return void
     */
    public function testTriggering(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::CREATING, $eventDispatcher);
        $users = User::factory()->count(2)->make();
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        self::spyShouldHaveReceived($listener)->times($users->count());
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
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $this->makeModelListenerWithReturningValue(
            $previousEventName,
            $eventDispatcher,
            [false, false]
        );
        $creatingListener = $this->makeSimpleModelListener(BulkEventEnum::CREATING, $eventDispatcher);
        $users = User::factory()->count(2)->make();
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher);

        // assert
        self::spyShouldNotHaveReceived($creatingListener);
    }

    /**
     * The listener for the 'creating' event should receive only one argument, which must be the model.
     *
     * @return void
     */
    public function testListenerArguments(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::CREATING, $eventDispatcher);
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
