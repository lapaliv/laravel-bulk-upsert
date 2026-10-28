<?php

namespace Tests\Unit\Events;

use Illuminate\Contracts\Events\Dispatcher;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\TestCaseWrapper;
use Tests\Unit\ModelListenerTestTrait;
use Tests\Unit\UserTestTrait;

/**
 * `BulkEventDispatcher::dispatch()` must route model events through the Illuminate dispatcher.
 *
 * Regression test. {@see \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher::dispatch()} used to read:
 *
 *     if (in_array($event->value, BulkEventEnum::model())) {
 *
 * `$event->value` is a `string`, while {@see \Lapaliv\BulkUpsert\Enums\BulkEventEnum::model()} returns an array of
 * `BulkEventEnum` cases (the `@return string[]` docblock is wrong). A string is never
 * loosely equal to an enum instance, so the condition is `false` for all 24 events and the branch
 * is dead. Compare with line 181, where the same idiom is written correctly:
 * `in_array($event, BulkEventEnum::halt(), true)`.
 *
 * Every event therefore falls into the `elseif`, which is guarded by
 * `method_exists($this->getEventDispatcher(), 'getListeners')`. `getListeners()` is not part of
 * {@see \Illuminate\Contracts\Events\Dispatcher} — it only exists on the concrete `Illuminate\Events\Dispatcher`. As soon as the
 * application swaps in a decorating dispatcher, which is exactly what `Event::fake()` (`EventFake`,
 * proxying through `__call()`) and `Model::withoutEvents()` (`NullDispatcher`) do, model listeners
 * stop being called while `hasListeners()` keeps reporting `true`.
 *
 * The suite did not notice because it always runs against the concrete dispatcher, which happens
 * to have that method. These tests install a dispatcher that implements the contract and nothing
 * more. The fix drops both branches in favour of a single `Dispatcher::dispatch($event, $payload,
 * $halt)` call, which spreads an array payload over the listener arguments and therefore serves the
 * model shape (`[$model]`) and the collection shape (`[$collection, $bulkRows]`) alike.
 *
 * @internal
 */
final class BulkEventDispatcherModelBranchTest extends TestCaseWrapper
{
    use UserTestTrait;
    use ModelListenerTestTrait {
        tearDown as private modelListenerTearDown;
    }

    /**
     * The dispatcher which the application used before the test replaced it.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher|null
     */
    private ?Dispatcher $originalDispatcher = null;

    /**
     * Put the original event dispatcher back before the Mockery doubles are closed.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        // Restore before Mockery::close(), the proxy below is a Mockery double.
        if ($this->originalDispatcher !== null) {
            BulkEventDispatcher::setIlluminateEventDispatcher($this->originalDispatcher);
            $this->originalDispatcher = null;
        }

        $this->modelListenerTearDown();
    }

    /**
     * `BulkEventEnum::model()` yields enum cases, so it may only be searched strictly.
     *
     * This is the trap the bug fell into: `in_array($event->value, BulkEventEnum::model())`
     * looks reasonable and is quietly always `false`, because a string is never loosely equal to an
     * enum instance. The guard is here so that a future change to `model()` — or a new caller
     * reaching for `->value` — is caught by a test rather than by silence.
     *
     * @return void
     */
    public function testModelHelperYieldsEnumCasesAndMustBeSearchedStrictly(): void
    {
        // arrange
        $events = BulkEventEnum::model();

        // assert
        self::assertNotEmpty($events);

        foreach ($events as $event) {
            self::assertInstanceOf(BulkEventEnum::class, $event);

            self::assertTrue(
                in_array($event, $events, true),
                'A case must be found when the array is searched with the case itself.'
            );

            self::assertFalse(
                in_array($event->value, $events),
                'A case is never found by its backing value, not even with a loose search. '
                . 'Compare against BulkEventEnum cases, never against $event->value.'
            );
        }
    }

    /**
     * An event still reaches its listener when the dispatcher only implements the contract.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     * @param int $expectedCalls
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('eventDataProvider')]
    public function testEventIsDispatchedThroughAContractOnlyDispatcher(
        BulkEventEnum $event,
        int $expectedCalls
    ): void {
        // arrange
        $this->useContractOnlyDispatcher();

        $users = $this->userGenerator->makeCollection(2);
        User::observe(Observer::class);
        $listener = $this->listenEvent($event);

        // act
        User::query()->bulk()->uniqueBy(['email'])->create($users);

        // assert
        self::spyShouldHaveReceived($listener)->times($expectedCalls);
    }

    /**
     * `hasListeners()` and `dispatch()` must agree with each other.
     *
     * They do not: `hasListeners()` asks the contract (`Dispatcher::hasListeners()`) and answers
     * `true`, while `dispatch()` silently drops the event because the dispatcher has no
     * `getListeners()`. Everything that branches on `hasListeners()` — for instance
     * {@see \Lapaliv\BulkUpsert\Scenarios\Create\InsertAndSelectStep::doNeedToSelect()} — is then
     * reasoning about listeners that will never run.
     *
     * @return void
     */
    public function testHasListenersAgreesWithDispatch(): void
    {
        // arrange
        $this->useContractOnlyDispatcher();

        User::observe(Observer::class);
        $listener = $this->listenEvent(BulkEventEnum::Saving);

        $sut = new BulkEventDispatcher(new User());
        $model = $this->userGenerator->makeOne();

        // act
        $hasListeners = $sut->hasListeners([BulkEventEnum::Saving]);
        $sut->dispatch(BulkEventEnum::Saving, $model);

        // assert
        self::assertTrue($hasListeners, 'Precondition: the observer is registered.');
        self::spyShouldHaveReceived($listener)->once();
    }

    /**
     * Both payload shapes: model events fire once per model, collection events once per batch.
     *
     * @return array<string, array{\Lapaliv\BulkUpsert\Enums\BulkEventEnum, int}>
     */
    public static function eventDataProvider(): array
    {
        return [
            'saving' => [BulkEventEnum::Saving, 2],
            'creating' => [BulkEventEnum::Creating, 2],
            'savingMany' => [BulkEventEnum::SavingMany, 1],
            'creatingMany' => [BulkEventEnum::CreatingMany, 1],
        ];
    }

    /**
     * Install a dispatcher that implements {@see \Illuminate\Contracts\Events\Dispatcher} and exposes nothing else.
     *
     * Built with Mockery so the double is generated from the contract itself: every contract
     * method exists with the right signature, and `getListeners()` — which is not on the contract —
     * does not, which is precisely the shape of `EventFake` and `NullDispatcher`. Calls are
     * forwarded to the real dispatcher, so nothing else about the run changes.
     *
     * @return void
     */
    private function useContractOnlyDispatcher(): void
    {
        /** @var \Illuminate\Contracts\Events\Dispatcher $origin */
        $origin = $this->app->make('events');

        $this->originalDispatcher = $origin;

        $proxy = Mockery::mock(Dispatcher::class);
        $proxy->shouldIgnoreMissing();
        $proxy->shouldReceive('listen')
            ->andReturnUsing(static fn ($events, $listener = null) => $origin->listen($events, $listener));
        $proxy->shouldReceive('hasListeners')
            ->andReturnUsing(static fn ($eventName) => $origin->hasListeners($eventName));
        $proxy->shouldReceive('until')
            ->andReturnUsing(static fn ($event, $payload = []) => $origin->until($event, $payload));
        $proxy->shouldReceive('dispatch')
            ->andReturnUsing(static fn ($event, $payload = [], $halt = false) => $origin->dispatch($event, $payload, $halt));

        self::assertFalse(
            method_exists($proxy, 'getListeners'),
            'Precondition: the proxy must expose the contract only.'
        );

        BulkEventDispatcher::setIlluminateEventDispatcher($proxy);
    }
}
