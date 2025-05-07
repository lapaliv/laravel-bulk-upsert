<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenario;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Tests\Unit\BulkAccumulationEntityTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\ModelListenerTestTrait;
use Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenarioTestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;

/**
 * @internal
 */
class DeletingManyEventTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * If the model has a listener for the 'deletingMany' event, then this listener should be called.
     *
     * @return void
     */
    public function testTriggering(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::DELETING_MANY, $eventDispatcher);
        $users = \Lapaliv\BulkUpsert\Tests\App\Models\User::factory()->count(2)->make(['deleted_at' => Carbon::now()]);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, deletedAtColumn: 'deleted_at');

        // assert
        self::spyShouldHaveReceived($listener)->once();
    }

    /**
     * If the model has a listener for the 'deletingMany' event, but 'deleted_at' is not filled in,
     * then this listener should not be invoked.
     *
     * @return void
     */
    public function testNotTriggeringWhenDeletedAtIsNull(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::DELETED_MANY, $eventDispatcher);
        $users = \Lapaliv\BulkUpsert\Tests\App\Models\User::factory()->count(2)->make(['deleted_at' => null]);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, deletedAtColumn: 'deleted_at');

        // assert
        self::spyShouldNotHaveReceived($listener);
    }

    /**
     * If the previous listener returns false, then the listener for the 'deletingMany' event must not be called.
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
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $this->makeModelListenerWithReturningValue(
            $previousEventName,
            $eventDispatcher,
            [false, false]
        );
        $deletingManyListener = $this->makeSimpleModelListener(BulkEventEnum::DELETING_MANY, $eventDispatcher);
        $users = \Lapaliv\BulkUpsert\Tests\App\Models\User::factory()->count(2)->make(['deleted_at' => Carbon::now()]);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, deletedAtColumn: 'deleted_at');

        // assert
        self::spyShouldNotHaveReceived($deletingManyListener);
    }

    /**
     * The listener for the 'deletingMany' event should receive two arguments:
     * the model and an object of the BulkRows class.
     *
     * @return void
     */
    public function testListenerArguments(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::DELETING_MANY, $eventDispatcher);
        $users = \Lapaliv\BulkUpsert\Tests\App\Models\User::factory()->count(2)->make(['deleted_at' => Carbon::now()]);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, deletedAtColumn: 'deleted_at');

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertCollectionListenerArguments($users, ...func_get_args())
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
            'creating' => [BulkEventEnum::CREATING],
            'creatingMany' => [BulkEventEnum::CREATING_MANY],
        ];
    }
}
