<?php

namespace Tests\Unit\Bulk\Create;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\App\Support\TestCallback;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;
use Mockery;

/**
 * The switching of the events on and off while `create()` writes the rows.
 *
 * @internal
 */
class CreateEventsTest extends TestCaseWrapper
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
        $users = $this->userGenerator->makeCollection(2);
        $sut = User::query()
            ->bulk()
            ->disableEvents()
            ->uniqueBy(['email']);

        $spy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listenAny($spy);

        // act
        $sut->create($users);

        // assert
        self::spyShouldNotHaveReceived($spy);
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
        $users = $this->userGenerator->makeCollection(2);
        $sut = User::query()
            ->bulk()
            ->disableEvents([$disabledEvent])
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listenAny($callingSpy);
        Observer::listen($disabledEvent, $notCallingSpy);

        // act
        $sut->create($users);

        // assert
//        dd($sut, $disabledEvent);
        self::spyShouldHaveReceived($callingSpy)
            ->atLeast()
            ->once();
        self::spyShouldNotHaveReceived($notCallingSpy);
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
        $modelEndEvents = BulkEventEnum::modelEnd();
        $users = $this->userGenerator->makeCollection(2);
        $sut = User::query()
            ->bulk()
            ->disableModelEndEvents()
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listenAny($callingSpy);

        foreach ($modelEndEvents as $event) {
            Observer::listen($event, $notCallingSpy);
        }

        // act
        $sut->create($users);

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
    public static function testDisableOneEvent(BulkEventEnum $disabledEvent): void
    {
        // arrange
        $users = User::factory()->count(2)->make();
        $sut = User::query()
            ->bulk()
            ->disableEvent($disabledEvent)
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listenAny($callingSpy);
        Observer::listen($disabledEvent, $notCallingSpy);

        // act
        $sut->create($users);

        // assert
        self::spyShouldHaveReceived($callingSpy)
            ->atLeast()
            ->once();
        self::spyShouldNotHaveReceived($notCallingSpy);
    }

    /**
     * A soft deleted row fires all save, create and delete events when the events are enabled.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testEnableAllEvents(): void
    {
        // arrange
        $users = $this->userGenerator
            ->makeCollection(1, [
                'deleted_at' => Carbon::now(),
            ]);
        $sut = User::query()
            ->bulk()
            ->enableEvents()
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listenAny($callingSpy);

        // 4 for saving
        // 4 for creating
        // 4 for deleting
        $countEventsPerModel = 4 * 3;

        // act
        $sut->create($users);

        // assert
        self::spyShouldHaveReceived($callingSpy)->times(
            $countEventsPerModel * $users->count()
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
        $users = $this->userGenerator->makeCollection(2);
        $sut = User::query()
            ->bulk()
            ->enableEvents()
            ->disableEvents([$enabledEvent])
            ->enableEvents([$enabledEvent])
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listen($enabledEvent, $callingSpy);

        // act
        $sut->create($users);

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
        $users = $this->userGenerator->makeCollection(2);
        $sut = User::query()
            ->bulk()
            ->disableEvents()
            ->enableEvents([$enabledEvent])
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listenAny($notCallingSpy, [$enabledEvent]);
        Observer::listen($enabledEvent, $callingSpy);

        // act
        $sut->create($users);

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
        $users = $this->userGenerator->makeCollection(2);
        $sut = User::query()
            ->bulk()
            ->disableEvents()
            ->enableEvent($enabledEvent)
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        User::observe(Observer::class);
        Observer::listenAny($notCallingSpy, [$enabledEvent]);
        Observer::listen($enabledEvent, $callingSpy);

        // act
        $sut->create($users);

        // assert
        self::spyShouldHaveReceived($callingSpy);
        self::spyShouldNotHaveReceived($notCallingSpy);
    }

    /**
     * The save and create events which `create()` can fire.
     *
     * @return array<int, array{\Lapaliv\BulkUpsert\Enums\BulkEventEnum}>
     */
    public static function eventsDataProvider(): array
    {
        return array_map(
            static fn(BulkEventEnum $event) => [$event],
            [
                ...BulkEventEnum::save(),
                ...BulkEventEnum::create(),
            ]
        );
    }
}
