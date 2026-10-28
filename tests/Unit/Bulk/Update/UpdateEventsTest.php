<?php

namespace Tests\Unit\Bulk\Update;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\App\Support\TestCallback;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;
use Mockery;

/**
 * The switching of the events on and off while `update()` writes the rows.
 *
 * @internal
 */
class UpdateEventsTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * No event is fired when `disableEvents()` turns all of them off.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDisableAllEvents(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
            ->bulk()
            ->disableEvents();

        $spy = Mockery::spy(TestCallback::class);
        User::observe(Observer::class);
        Observer::listenAny($spy);

        // act
        $sut->update($users);

        // assert
        self::spyShouldNotHaveReceived($spy);
    }

    /**
     * `disableModelEndEvents()` stops the model end events only, the other events still run.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDisableModelEndEvents(): void
    {
        // arrange
        $disabledEvents = BulkEventEnum::modelEnd();
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
            ->bulk()
            ->disableModelEndEvents();

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listenAny($callingSpy);

        foreach ($disabledEvents as $event) {
            Observer::listen($event, $notCallingSpy);
        }

        // act
        $sut->update($users);

        // assert
        self::spyShouldHaveReceived($callingSpy)
            ->atLeast()
            ->once();
        self::spyShouldNotHaveReceived($notCallingSpy);
    }

    /**
     * A single disabled event is not fired, while the other events still are.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $disabledEvent
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('eventsDataProvider')]
    public function testDisableSomeEvents(BulkEventEnum $disabledEvent): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
            ->bulk()
            ->disableEvents([$disabledEvent]);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listenAny($callingSpy);
        Observer::listen($disabledEvent, $notCallingSpy);

        // act
        $sut->update($users);

        // assert
        self::spyShouldHaveReceived($callingSpy)
            ->atLeast()
            ->once();
        self::spyShouldNotHaveReceived($notCallingSpy);
    }

    /**
     * An event turned off with `disableEvent()` is not fired, the other events still are.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $disabledEvent
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('eventsDataProvider')]
    public function testDisableOneEvent(BulkEventEnum $disabledEvent): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
            ->bulk()
            ->disableEvent($disabledEvent);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listenAny($callingSpy);
        Observer::listen($disabledEvent, $notCallingSpy);

        // act
        $sut->update($users);

        // assert
        self::spyShouldHaveReceived($callingSpy)
            ->atLeast()
            ->once();
        self::spyShouldNotHaveReceived($notCallingSpy);
    }

    /**
     * With all events enabled the save and update events run for every model, and the collection
     * ones only once per chunk.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testEnableAllEvents(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
            ->bulk()
            ->enableEvents();

        $spy = Mockery::spy(TestCallback::class);
        User::observe(Observer::class);
        Observer::listenAny($spy);
        $countOfCallingPerModel = count(BulkEventEnum::update()) + count(BulkEventEnum::save());

        // act
        $sut->update($users);

        // assert
        self::spyShouldHaveReceived($spy)
            ->times(
                $countOfCallingPerModel * $users->count()
                // The minus due to the collection events
                - ($users->count() * $users->count())
            );
    }

    /**
     * An event which is disabled and then enabled again is fired.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $enabledEvent
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('eventsDataProvider')]
    public function testEnableSomeDisabledEvents(BulkEventEnum $enabledEvent): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
            ->bulk()
            ->enableEvents()
            ->disableEvent($enabledEvent)
            ->enableEvents([$enabledEvent]);

        $callingSpy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listen($enabledEvent, $callingSpy);

        // act
        $sut->update($users);

        // assert
        self::spyShouldHaveReceived($callingSpy);
    }

    /**
     * After all events are disabled, only the event enabled with `enableEvents()` is fired.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $enabledEvent
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('eventsDataProvider')]
    public function testEnableSomeEvents(BulkEventEnum $enabledEvent): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
            ->bulk()
            ->disableEvents()
            ->enableEvents([$enabledEvent]);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listenAny($notCallingSpy, [$enabledEvent]);
        Observer::listen($enabledEvent, $callingSpy);

        // act
        $sut->update($users);

        // assert
        self::spyShouldHaveReceived($callingSpy);
        self::spyShouldNotHaveReceived($notCallingSpy);
    }

    /**
     * After all events are disabled, only the event enabled with `enableEvent()` is fired.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $enabledEvent
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('eventsDataProvider')]
    public function testEnableOneEvent(BulkEventEnum $enabledEvent): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(2);
        $sut = User::query()
            ->bulk()
            ->disableEvents()
            ->enableEvent($enabledEvent);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listenAny($notCallingSpy, [$enabledEvent]);
        Observer::listen($enabledEvent, $callingSpy);

        // act
        $sut->update($users);

        // assert
        self::spyShouldHaveReceived($callingSpy);
        self::spyShouldNotHaveReceived($notCallingSpy);
    }

    /**
     * The save and update events which `update()` can fire.
     *
     * @return array<int, array{\Lapaliv\BulkUpsert\Enums\BulkEventEnum}>
     */
    public static function eventsDataProvider(): array
    {
        return array_map(
            fn(BulkEventEnum $event) => [$event],
            [
                ...BulkEventEnum::save(),
                ...BulkEventEnum::update(),
            ]
        );
    }
}
