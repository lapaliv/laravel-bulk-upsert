<?php

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Illuminate\Support\Facades\Event;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\FireModelEventsFeature;
use Lapaliv\BulkUpsert\Features\GetEloquentNativeEventNameFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

final class FireModelEventsFeatureTest extends TestCase
{
    private GetEloquentNativeEventNameFeature $getEloquentNativeEventNameFeature;

    /**
     * @param string $event
     * @return void
     * @dataProvider dispatchedDataProvider
     */
    public function testDispatched(string $event): void
    {
        // arrange
        Event::fake();
        $model = new MySqlUser();
        /** @var FireModelEventsFeature $sut */
        $sut = $this->app->make(FireModelEventsFeature::class);

        // act
        $sut->handle($model, [$event], [$event]);

        // assert
        Event::assertDispatched(
            $this->getEloquentNativeEventNameFeature->handle($model::class, $event)
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
        $model = new MySqlUser();
        /** @var FireModelEventsFeature $sut */
        $sut = $this->app->make(FireModelEventsFeature::class);

        // act
        $sut->handle($model, [], [$event]);

        // assert
        Event::assertNotDispatched(
            $this->getEloquentNativeEventNameFeature->handle($model, $event)
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
        Event::fake();
        $model = new MySqlUser();
        MySqlUser::saving(static fn () => false);
        MySqlUser::creating(static fn () => false);

        /** @var FireModelEventsFeature $sut */
        $sut = $this->app->make(FireModelEventsFeature::class);

        // act
        $sut->handle(
            $model,
            [BulkEventEnum::SAVING, BulkEventEnum::CREATING, BulkEventEnum::CREATED, BulkEventEnum::SAVED],
            [$dispatchedEvent]
        );

        // assert
        Event::assertDispatched(
            $this->getEloquentNativeEventNameFeature->handle($model::class, $dispatchedEvent)
        );
        Event::assertNotDispatched(
            $this->getEloquentNativeEventNameFeature->handle($model::class, $notDispatchedEvent)
        );
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

        $this->getEloquentNativeEventNameFeature = $this->app->make(GetEloquentNativeEventNameFeature::class);
    }
}
