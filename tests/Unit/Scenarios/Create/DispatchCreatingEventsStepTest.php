<?php

namespace Tests\Unit\Scenarios\Create;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * The `creating` and `creatingMany` events fired while creating, verified through the public bulk API.
 *
 * @internal
 */
final class DispatchCreatingEventsStepTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait;

    /**
     * The listener is invoked once per created model.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Creating);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->times($users->count());
    }

    /**
     * When an earlier event cancels the save, `creating` is never reached.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $cancellingEvent
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('cancellingEventDataProvider')]
    public function testNotTriggeringWhenPreviousEventReturnedFalse(BulkEventEnum $cancellingEvent): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $this->listenEventReturning($cancellingEvent, [false, false]);
        $listener = $this->listenEvent(BulkEventEnum::Creating);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldNotHaveReceived($listener);
    }

    /**
     * The listener receives a single argument: the model being created.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Creating);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertModelListenerArguments($users, ...func_get_args())
            );
    }

    /**
     * Events that run before `creating` and can cancel the save.
     *
     * @return array<string, array{\Lapaliv\BulkUpsert\Enums\BulkEventEnum}>
     */
    public static function cancellingEventDataProvider(): array
    {
        return [
            'saving' => [BulkEventEnum::Saving],
            'savingMany' => [BulkEventEnum::SavingMany],
        ];
    }

    /**
     * The listener is invoked once for the whole chunk.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testManyTriggering(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::CreatingMany);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->once();
    }

    /**
     * When an earlier event cancels the save, `creatingMany` is never reached.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $cancellingEvent
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('cancellingEventDataProvider')]
    public function testManyNotTriggeringWhenPreviousEventReturnedFalse(BulkEventEnum $cancellingEvent): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $this->listenEventReturning($cancellingEvent, [false, false]);
        $listener = $this->listenEvent(BulkEventEnum::CreatingMany);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldNotHaveReceived($listener);
    }

    /**
     * The listener receives two arguments: the collection and the BulkRowCollection object.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testManyListenerArguments(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::CreatingMany);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)
            ->withArgs(
                fn() => $this->assertCollectionListenerArguments($users, ...func_get_args())
            );
    }

    /**
     * A `creating` veto drops exactly the model it was called with.
     *
     * With no preceding veto the row indexes line up trivially, so this is the control case:
     * it pins the behaviour the shifted variant below has to match.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCancelsOnlyTheVetoedModel(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(3);
        User::observe(Observer::class);

        // approve #0, veto #1, approve #2
        $this->listenEventReturning(BulkEventEnum::Creating, [true, false, true]);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        $this->userWasCreated($users[0]);
        $this->userWasNotCreated($users[1]);
        $this->userWasCreated($users[2]);
    }

    /**
     * A preceding `saving` veto must not change which model `creating` cancels.
     *
     * Regression test. {@see \Lapaliv\BulkUpsert\Scenarios\Create\DispatchCreatingEventsStep}
     * used to build its working set with
     *
     *     $models = $data->getModels($filter);
     *
     * and {@see \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity::getModels()} ends with
     * `->values()`, so the collection is re-indexed from zero. The keys of that collection were then
     * fed straight back into the unfiltered row array:
     *
     *     foreach ($models as $key => $model) {
     *         if ($dispatchingResult === false) {
     *             $data->unsetRow($key);   // $key indexes $models, not $data->rows
     *         }
     *     }
     *
     * The two index spaces line up only while `$data->rows` is a gap-free list starting at zero.
     * {@see \Lapaliv\BulkUpsert\Scenarios\Create\DispatchSavingEventsStep} runs immediately before
     * and punches holes in it with `unsetRow()` using the real keys, which is enough to shift
     * everything. From that point on, a `creating` veto removed an unrelated row and let the vetoed
     * one through. The step now iterates `$data->getRows()` directly and applies the filter per row,
     * so the key it cancels is always the key it dispatched.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCancelsTheCorrectModelAfterAPrecedingSavingVeto(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(3);
        User::observe(Observer::class);

        // `saving` drops #0, which leaves $data->rows keyed [1 => .., 2 => ..]
        $this->listenEventReturning(BulkEventEnum::Saving, [false, true, true]);

        // `creating` then sees a re-indexed [0 => user#1, 1 => user#2]: approve #1, veto #2
        $this->listenEventReturning(BulkEventEnum::Creating, [true, false]);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        $this->userWasNotCreated($users[0]);
        $this->userWasCreated($users[1]);
        $this->userWasNotCreated($users[2]);
    }
}
