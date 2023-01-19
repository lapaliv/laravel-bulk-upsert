<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Illuminate\Support\Facades\Event;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\FireModelEventsFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\TestCase;

class FireModelEventsFeatureTest extends TestCase
{
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
        Event::assertDispatched("eloquent.{$event}: " . $model::class);
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
        Event::assertNotDispatched("eloquent.{$event}: " . $model::class);
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
        Event::assertDispatched("eloquent.{$dispatchedEvent}: " . $model::class);
        Event::assertNotDispatched("eloquent.{$notDispatchedEvent}: " . $model::class);
    }

    public function dispatchedDataProvider(): array
    {
        return [
            'creating' => [BulkEventEnum::CREATING],
            'created' => [BulkEventEnum::CREATED],
            'saving' => [BulkEventEnum::SAVING],
            'saved' => [BulkEventEnum::SAVED],
        ];
    }

    public function notDispatchedDataProvider(): array
    {
        return [
            'updating' => [BulkEventEnum::UPDATING],
            'updated' => [BulkEventEnum::UPDATED],
        ];
    }

    public function stopPropagationDataProvider(): array
    {
        return [
            'saving' => [BulkEventEnum::SAVING, BulkEventEnum::CREATING],
            'creating' => [BulkEventEnum::CREATING, BulkEventEnum::CREATED],
        ];
    }
}
