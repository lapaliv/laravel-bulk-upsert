<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;

/**
 * @internal
 */
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

        // Firstly, it needs to fire the 'saving' event if there are some listeners.
        // If the event dispatcher returns false, the row won't be created.
        $this->processEachRow($data, $eventDispatcher);

        // Secondly, it needs to fire the 'savingMany' event if there are some listeners.
        // If the event dispatcher returns false, all the rows won't be created.
        if ($data->hasRows()) {
            $this->processAllRows($data, $eventDispatcher);
        }
    }

    /**
     * Process each row.
     *
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     *
     * @return void
     */
    private function processEachRow(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        foreach ($data->getRows() as $key => $accumulatedRow) {
            $dispatchingResult = $eventDispatcher->dispatch(
                BulkEventEnum::SAVING,
                $accumulatedRow->getModel()
            );

            if ($dispatchingResult === false) {
                $data->unsetRow($key);
            }
        }
    }

    /**
     * Process all the provided rows.
     *
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     *
     * @return void
     */
    private function processAllRows(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
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
