<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Observers\Observer;
use Lapaliv\BulkUpsert\Tests\App\Support\TestCallback;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;
use Mockery;

/**
 * @internal
 */
class UpdateEventsTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testDisableAllEvents(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->disableEvents();

        $spy = Mockery::spy(TestCallback::class);
        $model::observe(Observer::class);
        Observer::listenAny($spy);

        // act
        $sut->update($users);

        // assert
        $this->spyShouldNotHaveReceived($spy);
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testDisableModelEndEvents(string $model): void
    {
        // arrange
        $disabledEvents = BulkEventEnum::modelEnd();
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->disableModelEndEvents();

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listenAny($callingSpy);

        foreach ($disabledEvents as $event) {
            Observer::listen($event, $notCallingSpy);
        }

        // act
        $sut->update($users);

        // assert
        $this->spyShouldHaveReceived($callingSpy)
            ->atLeast()
            ->once();
        $this->spyShouldNotHaveReceived($notCallingSpy);
    }

    /**
     * @param class-string<User> $model
     * @param string $disabledEvent
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider eventsDataProvider
     */
    public function testDisableSomeEvents(string $model, string $disabledEvent): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->disableEvents([$disabledEvent]);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listenAny($callingSpy);
        Observer::listen($disabledEvent, $notCallingSpy);

        // act
        $sut->update($users);

        // assert
        $this->spyShouldHaveReceived($callingSpy)
            ->atLeast()
            ->once();
        $this->spyShouldNotHaveReceived($notCallingSpy);
    }

    /**
     * @param class-string<User> $model
     * @param string $disabledEvent
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider eventsDataProvider
     */
    public function testDisableOneEvent(string $model, string $disabledEvent): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->disableEvent($disabledEvent);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listenAny($callingSpy);
        Observer::listen($disabledEvent, $notCallingSpy);

        // act
        $sut->update($users);

        // assert
        $this->spyShouldHaveReceived($callingSpy)
            ->atLeast()
            ->once();
        $this->spyShouldNotHaveReceived($notCallingSpy);
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider userModelsDataProvider
     */
    public function testEnableAllEvents(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->enableEvents();

        $spy = Mockery::spy(TestCallback::class);
        $model::observe(Observer::class);
        Observer::listenAny($spy);
        $countOfCallingPerModel = count(BulkEventEnum::update()) + count(BulkEventEnum::save());

        // act
        $sut->update($users);

        // assert
        $this->spyShouldHaveReceived($spy)
            ->times(
                $countOfCallingPerModel * $users->count()
                // The minus due to the collection events
                - ($users->count() * $users->count())
            );
    }

    /**
     * @param class-string<User> $model
     * @param string $enabledEvent
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider eventsDataProvider
     */
    public function testEnableSomeDisabledEvents(string $model, string $enabledEvent): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->enableEvents()
            ->disableEvent($enabledEvent)
            ->enableEvents([$enabledEvent]);

        $callingSpy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listen($enabledEvent, $callingSpy);

        // act
        $sut->update($users);

        // assert
        $this->spyShouldHaveReceived($callingSpy);
    }

    /**
     * @param class-string<User> $model
     * @param string $enabledEvent
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider eventsDataProvider
     */
    public function testEnableSomeEvents(string $model, string $enabledEvent): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->disableEvents()
            ->enableEvents([$enabledEvent]);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listenAny($notCallingSpy, [$enabledEvent]);
        Observer::listen($enabledEvent, $callingSpy);

        // act
        $sut->update($users);

        // assert
        $this->spyShouldHaveReceived($callingSpy);
        $this->spyShouldNotHaveReceived($notCallingSpy);
    }

    /**
     * @param class-string<User> $model
     * @param string $enabledEvent
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider eventsDataProvider
     */
    public function testEnableOneEvent(string $model, string $enabledEvent): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->createCollectionAndDirty(2);
        $sut = $model::query()
            ->bulk()
            ->disableEvents()
            ->enableEvent($enabledEvent);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listenAny($notCallingSpy, [$enabledEvent]);
        Observer::listen($enabledEvent, $callingSpy);

        // act
        $sut->update($users);

        // assert
        $this->spyShouldHaveReceived($callingSpy);
        $this->spyShouldNotHaveReceived($notCallingSpy);
    }

    public function eventsDataProvider(): array
    {
        $targetEvents = [
            ...BulkEventEnum::save(),
            ...BulkEventEnum::update(),
        ];

        $result = [];

        foreach ($this->userModelsDataProvider() as $key => [$model]) {
            foreach ($targetEvents as $targetEvent) {
                $result[$targetEvent . ' && ' . $key] = [$model, $targetEvent];
            }
        }

        return $result;
    }

    public function userModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class],
            'postgre' => [PostgreSqlUser::class],
        ];
    }
}
