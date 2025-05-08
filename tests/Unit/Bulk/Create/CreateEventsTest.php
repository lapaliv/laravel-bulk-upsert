<?php

namespace Tests\Unit\Bulk\Create;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Models\User;
use Tests\App\Observers\Observer;
use Tests\App\Support\TestCallback;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;
use Mockery;

/**
 * @internal
 */
class CreateEventsTest extends TestCaseWrapper
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
     * @param string $disabledEvent
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider eventsDataProvider
     */
    public static function testDisableOneEvent(string $disabledEvent): void
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
     * @return void
     *
     * @throws BulkException
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

    public static function eventsDataProvider(): array
    {
        return array_map(
            static fn(string $event) => [$event],
            [
                ...BulkEventEnum::save(),
                ...BulkEventEnum::create(),
            ]
        );
    }
}
