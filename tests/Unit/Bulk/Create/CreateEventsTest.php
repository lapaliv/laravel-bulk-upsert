<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Create;

use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Observers\Observer;
use Lapaliv\BulkUpsert\Tests\App\Support\TestCallback;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;
use Mockery;

/**
 * @internal
 */
class CreateEventsTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     *
     * @throws BulkException
     */
    public function testDisableAllEvents(string $model): void
    {
        // arrange
        $users = $this->userGenerator
            ->setModel($model)
            ->makeCollection(2);
        $sut = $model::query()
            ->bulk()
            ->disableEvents()
            ->uniqueBy(['email']);

        $spy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listenAny($spy);

        // act
        $sut->create($users);

        // assert
        $this->spyShouldNotHaveReceived($spy);
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
            ->makeCollection(2);
        $sut = $model::query()
            ->bulk()
            ->disableEvents([$disabledEvent])
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listenAny($callingSpy);
        Observer::listen($disabledEvent, $notCallingSpy);

        // act
        $sut->create($users);

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
    public function testDisableModelEndEvents(string $model): void
    {
        // arrange
        $modelEndEvents = BulkEventEnum::modelEnd();
        $users = $this->userGenerator
            ->setModel($model)
            ->makeCollection(2);
        $sut = $model::query()
            ->bulk()
            ->disableModelEndEvents()
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listenAny($callingSpy);

        foreach ($modelEndEvents as $event) {
            Observer::listen($event, $notCallingSpy);
        }

        // act
        $sut->create($users);

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
            ->makeCollection(2);
        $sut = $model::query()
            ->bulk()
            ->disableEvent($disabledEvent)
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listenAny($callingSpy);
        Observer::listen($disabledEvent, $notCallingSpy);

        // act
        $sut->create($users);

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
            ->makeCollection(2);
        $sut = $model::query()
            ->bulk()
            ->enableEvents()
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listenAny($callingSpy);

        $countEventsPerModel = count(BulkEventEnum::create()) + count(BulkEventEnum::save());

        // act
        $sut->create($users);

        // assert
        $this->spyShouldHaveReceived($callingSpy)->times(
            $countEventsPerModel * $users->count()
            // Minus due to the collection events
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
            ->makeCollection(2);
        $sut = $model::query()
            ->bulk()
            ->enableEvents()
            ->disableEvents([$enabledEvent])
            ->enableEvents([$enabledEvent])
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listen($enabledEvent, $callingSpy);

        // act
        $sut->create($users);

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
            ->makeCollection(2);
        $sut = $model::query()
            ->bulk()
            ->disableEvents()
            ->enableEvents([$enabledEvent])
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listenAny($notCallingSpy, [$enabledEvent]);
        Observer::listen($enabledEvent, $callingSpy);

        // act
        $sut->create($users);

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
            ->makeCollection(2);
        $sut = $model::query()
            ->bulk()
            ->disableEvents()
            ->enableEvent($enabledEvent)
            ->uniqueBy(['email']);

        $callingSpy = Mockery::spy(TestCallback::class);
        $notCallingSpy = Mockery::spy(TestCallback::class);

        $model::observe(Observer::class);
        Observer::listenAny($notCallingSpy, [$enabledEvent]);
        Observer::listen($enabledEvent, $callingSpy);

        // act
        $sut->create($users);

        // assert
        $this->spyShouldHaveReceived($callingSpy);
        $this->spyShouldNotHaveReceived($notCallingSpy);
    }

    public function eventsDataProvider(): array
    {
        $targetEvents = [
            ...BulkEventEnum::save(),
            ...BulkEventEnum::create(),
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
            'pgsql' => [PostgreSqlUser::class],
            'sqlite' => [SqLiteUser::class],
        ];
    }
}
