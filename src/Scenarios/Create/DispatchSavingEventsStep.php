<?php

namespace Lapaliv\BulkUpsert\Scenarios\Create;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;

/**
 * Dispatches the 'saving' events before the rows are inserted.
 *
 * @internal
 */
class DispatchSavingEventsStep
{
    /**
     * Dispatch 'saving' and 'savingMany' events for provided $data->getRows().
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     *
     * @return void
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher
    ): void
    {
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
     * Fire the 'saving' event for each row.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     *
     * @return void
     */
    private function processEachRow(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void
    {
        foreach ($data->getRows() as $key => $accumulatedRow) {
            /** @psalm-suppress MixedAssignment */
            $dispatchingResult = $eventDispatcher->dispatch(
                BulkEventEnum::Saving,
                $accumulatedRow->getModel()
            );

            if ($dispatchingResult === false) {
                // Recorded on the row itself: an upsert must not write it by its update either.
                $accumulatedRow->setSkipCreating(true);
                $data->unsetRow((int) $key);
            }
        }
    }

    /**
     * Fire the 'savingMany' event for all the rows.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     *
     * @return void
     */
    private function processAllRows(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void
    {
        /** @psalm-suppress MixedAssignment */
        $dispatchingResult = $eventDispatcher->dispatch(
            BulkEventEnum::SavingMany,
            $data->getModels(),
            $data->getBulkRows()
        );

        if ($dispatchingResult === false) {
            foreach ($data->getRows() as $row) {
                $row->setSkipCreating(true);
            }

            $data->setRows([]);
        }
    }
}
