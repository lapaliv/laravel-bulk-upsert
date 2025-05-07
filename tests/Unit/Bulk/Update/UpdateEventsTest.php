<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Observers\Observer;
use Lapaliv\BulkUpsert\Tests\App\Support\TestCallback;
use Lapaliv\BulkUpsert\Tests\TestCaseWrapper;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;
use Mockery;

/**
 * @internal
 */
class UpdateEventsTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @return void
     *
     * @throws BulkException
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
     * @return void
     *
     * @throws BulkException
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
     * @param string $disabledEvent
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider eventsDataProvider
     */
    public function testDisableSomeEvents(string $disabledEvent): void
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
     * @param string $disabledEvent
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider eventsDataProvider
     */
    public function testDisableOneEvent(string $disabledEvent): void
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
     * @return void
     *
     * @throws BulkException
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
     * @param string $enabledEvent
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider eventsDataProvider
     */
    public function testEnableSomeDisabledEvents(string $enabledEvent): void
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
     * @param string $enabledEvent
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider eventsDataProvider
     */
    public function testEnableSomeEvents(string $enabledEvent): void
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
     * @param string $enabledEvent
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider eventsDataProvider
     */
    public function testEnableOneEvent(string $enabledEvent): void
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

    public function eventsDataProvider(): array
    {
        return array_map(
            fn(string $event) => [$event],
            [
                ...BulkEventEnum::save(),
                ...BulkEventEnum::update(),
            ]
        );
    }
}
