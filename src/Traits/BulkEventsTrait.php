<?php

namespace Lapaliv\BulkUpsert\Traits;

use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

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

    abstract protected function getDefaultEvents(): array;
}
