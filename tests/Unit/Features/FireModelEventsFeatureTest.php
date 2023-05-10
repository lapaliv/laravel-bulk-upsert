<?php

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Illuminate\Support\Facades\Event;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\FireModelEventsFeature;
use Lapaliv\BulkUpsert\Features\GetEloquentNativeEventNamesFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateSpyListenersTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\SetModelEventSpyListenersTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithAutoIncrement;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;

final class FireModelEventsFeatureTest extends UnitTestCase
{
    private GetEloquentNativeEventNamesFeature $getEloquentNativeEventNamesFeature;
    private GenerateSpyListenersTestFeature $generateSpyListenersTestFeature;
    private SetModelEventSpyListenersTestFeature $setModelEventSpyListenersTestFeature;

    /**
     * @param string $event
     * @return void
     * @dataProvider dispatchedDataProvider
     */
    public function testDispatched(string $event): void
    {
        // arrange
        Event::fake();
        $model = new MySqlEntityWithAutoIncrement();
        $model::registerModelEvent($event, fn () => true);
        $dispatchingEvents = $this->getEloquentNativeEventNamesFeature->handle($model, [$event]);
        /** @var FireModelEventsFeature $sut */
        $sut = $this->app->make(FireModelEventsFeature::class);

        // act
        $sut->handle($model, [$event], [$event]);

        // assert
        self::assertCount(1, $dispatchingEvents);
        Event::assertDispatched(
            sprintf('eloquent.%s: %s', $dispatchingEvents[0], get_class($model))
        );
    }

    /**
     * @param string $event
     * @return void
     * @dataProvider notDispatchedDataProvider
     */
    public function testNotDispatched(string $event): void
    {
        // arrange
        Event::fake();
        $model = new MySqlEntityWithAutoIncrement();
        $model::registerModelEvent($event, fn () => true);
        $dispatchingEvents = $this->getEloquentNativeEventNamesFeature->handle($model, [$event]);
        /** @var FireModelEventsFeature $sut */
        $sut = $this->app->make(FireModelEventsFeature::class);

        // act
        $sut->handle($model, [], [$event]);

        // assert
        self::assertCount(1, $dispatchingEvents);
        Event::assertNotDispatched(
            sprintf('eloquent.%s: %s', $dispatchingEvents[0], get_class($model))
        );
    }

    /**
     * @param string $dispatchedEvent
     * @param string $notDispatchedEvent
     * @return void
     * @dataProvider stopPropagationDataProvider
     */
    public function testStopPropagation(string $dispatchedEvent, string $notDispatchedEvent): void
    {
        // arrange
        $model = MySqlEntityWithAutoIncrement::class;
        $listeners = $this->generateSpyListenersTestFeature->handle();
        $this->setModelEventSpyListenersTestFeature->handle($model, $listeners);

        /** @var FireModelEventsFeature $sut */
        $sut = $this->app->make(FireModelEventsFeature::class);

        // act
        $sut->handle(
            new $model(),
            [BulkEventEnum::SAVING, BulkEventEnum::CREATING, BulkEventEnum::CREATED, BulkEventEnum::SAVED],
            [$dispatchedEvent]
        );

        // assert
        $listeners[$dispatchedEvent]->shouldHaveReceived('__invoke');
        $listeners[$notDispatchedEvent]->shouldNotHaveReceived('__invoke');
    }

    /**
     * @return array[]
     */
    public function dispatchedDataProvider(): array
    {
        return [
            'creating' => [BulkEventEnum::CREATING],
            'created' => [BulkEventEnum::CREATED],
            'saving' => [BulkEventEnum::SAVING],
            'saved' => [BulkEventEnum::SAVED],
        ];
    }

    /**
     * @return array[]
     */
    public function notDispatchedDataProvider(): array
    {
        return [
            'updating' => [BulkEventEnum::UPDATING],
            'updated' => [BulkEventEnum::UPDATED],
        ];
    }

    /**
     * @return array[]
     */
    public function stopPropagationDataProvider(): array
    {
        return [
            'saving' => [BulkEventEnum::SAVING, BulkEventEnum::CREATING],
            'creating' => [BulkEventEnum::CREATING, BulkEventEnum::CREATED],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->getEloquentNativeEventNamesFeature = $this->app->make(GetEloquentNativeEventNamesFeature::class);
        $this->generateSpyListenersTestFeature = $this->app->make(GenerateSpyListenersTestFeature::class);
        $this->setModelEventSpyListenersTestFeature = $this->app->make(SetModelEventSpyListenersTestFeature::class);
    }
}
