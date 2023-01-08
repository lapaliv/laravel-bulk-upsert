<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

class BulkFireModelEventsFeature
{
    /**
     * @param BulkModel $model
     * @param string[] $allowedEvents
     * @param string[] $events
     * @return bool
     */
    public function handle(BulkModel $model, array $allowedEvents, array $events): bool
    {
        $finalEvents = array_intersect($events, $allowedEvents);

        foreach ($finalEvents as $event) {
            if (in_array($event, $this->getHaltEvents(), true)
                && $model->fireModelEvent($event) === false
            ) {
                return false;
            }

            if (in_array($event, $this->getNotHaltEvents(), true)) {
                $model->fireModelEvent($event, false);
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function getHaltEvents(): array
    {
        return [
            BulkEventEnum::SAVING,
            BulkEventEnum::CREATING,
            BulkEventEnum::UPDATING,
        ];
    }

    /**
     * @return string[]
     */
    private function getNotHaltEvents(): array
    {
        return [
            BulkEventEnum::CREATED,
            BulkEventEnum::UPDATED,
            BulkEventEnum::SAVED,
        ];
    }
}
