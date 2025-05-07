<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Scenarios\CreateScenario;

use Carbon\Carbon;
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
class DeletingEventTest extends CreateScenarioTestCase
{
    use BulkAccumulationEntityTestTrait;
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * If the model has a listener for the 'deleting' event, then this listener should be called.
     *
     * @return void
     */
    public function testTriggering(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::DELETING, $eventDispatcher);
        $users = User::factory()->count(2)->make(['deleted_at' => Carbon::now()]);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, deletedAtColumn: 'deleted_at');

        // assert
        self::spyShouldHaveReceived($listener)->times($users->count());
    }

    /**
     * If the model has a listener for the 'deleting' event, but 'deleted_at' is not filled in, then this listener should not be invoked.
     *
     * @return void
     */
    public function testNotTriggeringWhenDeletedAtIsNull(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::DELETING, $eventDispatcher);
        $users = User::factory()->count(2)->make(['deleted_at' => null]);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, deletedAtColumn: 'deleted_at');

        // assert
        self::spyShouldNotHaveReceived($listener);
    }

    /**
     * If the previous listener returns false, then the listener for the 'deleting' event must not be called.
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
        $deletingListener = $this->makeSimpleModelListener(BulkEventEnum::DELETING, $eventDispatcher);
        $users = User::factory()->count(2)->make(['deleted_at' => Carbon::now()]);
        $data = $this->getBulkAccumulationEntityFromCollection($users, ['email']);

        // act
        $this->handleCreateScenario($data, $eventDispatcher, deletedAtColumn: 'deleted_at');

        // assert
        self::spyShouldNotHaveReceived($deletingListener);
    }

    /**
     * The listener for the 'deleting' event should receive only one argument, which must be the model.
     *
     * @return void
     */
    public function testListenerArguments(): void
    {
        // arrange
        $eventDispatcher = new BulkEventDispatcher(User::class);
        $listener = $this->makeSimpleModelListener(BulkEventEnum::DELETING, $eventDispatcher);
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
            'creating' => [BulkEventEnum::CREATING],
            'creatingMany' => [BulkEventEnum::CREATING_MANY],
        ];
    }
}
