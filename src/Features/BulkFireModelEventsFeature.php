<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

class BulkFireModelEventsFeature
{
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

    private function getHaltEvents(): array
    {
        return [
            BulkEventEnum::SAVING,
            BulkEventEnum::CREATING,
            BulkEventEnum::UPDATING,
        ];
    }

    private function getNotHaltEvents(): array
    {
        return [
            BulkEventEnum::CREATED,
            BulkEventEnum::UPDATED,
            BulkEventEnum::SAVED,
        ];
    }
}
