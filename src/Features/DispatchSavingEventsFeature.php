<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;

class DispatchSavingEventsFeature
{
    /**
     * Dispatch 'saving' and 'savingMany' events for provided $data->getRows().
     *
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     *
     * @return void
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher
    ): void {
        if (!$data->hasRows()) {
            return;
        }

        if (!$eventDispatcher->hasListeners(BulkEventEnum::saving())) {
            return;
        }

        // Firstly, it needs to fire the 'saving' event.
        // If the event dispatcher returns false, the row won't be created.
        foreach ($data->getRows() as $key => $accumulatedRow) {
            if (!$this->processRow($accumulatedRow, $eventDispatcher)) {
                // When the event "saving" returns false then we should stop saving the model
                $data->unsetRow($key);
            }
        }

        // Secondly, it needs to fire the 'savingMany' event.
        // If the event dispatcher returns false, all the rows won't be created.
        $this->processCollection($data, $eventDispatcher);
    }

    /**
     * Process the provided row.
     *
     * @param BulkAccumulationItemEntity $accumulatedRow
     * @param BulkEventDispatcher $eventDispatcher
     *
     * @return bool
     */
    private function processRow(
        BulkAccumulationItemEntity $accumulatedRow,
        BulkEventDispatcher $eventDispatcher,
    ): bool {
        $dispatchingResult = $eventDispatcher->dispatch(
            BulkEventEnum::SAVING,
            $accumulatedRow->getModel()
        );

        return $dispatchingResult !== false;
    }

    /**
     * Process all the provided rows.
     *
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     *
     * @return void
     */
    private function processCollection(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        if (!$data->hasRows()) {
            return;
        }

        $dispatchingResult = $eventDispatcher->dispatch(
            BulkEventEnum::SAVING_MANY,
            $data->getModels(),
            $data->getBulkRows()
        );

        if ($dispatchingResult === false) {
            $data->setRows([]);
        }
    }
}
