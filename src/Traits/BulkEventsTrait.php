<?php

namespace Lapaliv\BulkUpsert\Traits;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Features\GetEloquentNativeEventNameFeature;

trait BulkEventsTrait
{
    /**
     * @var string[]
     */
    private array $events = [];

    /**
     * @return string[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param string[] $events
     * @return $this
     */
    public function setEvents(array $events): static
    {
        $this->events = array_intersect($events, $this->getDefaultEvents());

        return $this;
    }

    public function disableEvents(): static
    {
        $this->events = [];

        return $this;
    }

    protected function getIntersectEventsWithDispatcher(
        BulkModel $model,
        GetEloquentNativeEventNameFeature $getEloquentNativeEventNameFeature,
    ): array {
        return array_filter(
            $this->getEvents(),
            static fn (string $event) => $model::getEventDispatcher()->hasListeners(
                $getEloquentNativeEventNameFeature->handle(get_class($model), $event)
            )
        );
    }

    abstract protected function getDefaultEvents(): array;
}
