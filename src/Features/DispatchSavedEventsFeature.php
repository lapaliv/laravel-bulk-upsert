<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;

class DispatchSavedEventsFeature
{
    /**
     * Dispatch 'saved', 'savedMany' events for provided $data->getRows().
     *
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     *
     * @return void
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        if (!$data->hasRows()) {
            return;
        }

        if (!$eventDispatcher->hasListeners(BulkEventEnum::saved())) {
            return;
        }

        // The 'saved' event is fired anyway for all accumulated rows.
        // In this case `$data->getRows()` contains only the rows that participate in the query.
        foreach ($data->getRows() as $accumulatedRow) {
            $eventDispatcher->dispatch(BulkEventEnum::SAVED, $accumulatedRow->getModel());
        }

        $eventDispatcher->dispatch(
            BulkEventEnum::SAVED_MANY,
            $data->getModels(),
            $data->getBulkRows(),
        );
    }
}
