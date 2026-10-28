<?php

namespace Lapaliv\BulkUpsert\Scenarios\Update;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Collections\BulkRowCollection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;

/**
 * Dispatches the 'updating' events before the rows are updated.
 *
 * @internal
 */
class DispatchUpdatingEventsStep
{
    /**
     * Dispatch the 'updating' and 'updatingMany' events for provided $data->getRows().
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     *
     * @return void
     */
    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void
    {
        if (!$eventDispatcher->hasListeners(BulkEventEnum::updating())) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $models */
        $models = $eloquent->newCollection();
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $bulkRows */
        $bulkRows = new BulkRowCollection();

        // Firstly, it needs to fire the 'updating' event for each row
        // which has not been skipped by the previous steps.
        // If the event dispatcher returns false, the row won't be updated.
        foreach ($data->getRows() as $row) {
            if ($row->isSkipSaving() || $row->isSkipUpdating()) {
                continue;
            }

            if ($eventDispatcher->dispatch(BulkEventEnum::Updating, $row->getModel()) === false) {
                $row->setSkipUpdating(true);

                continue;
            }

            $models->push($row->getModel());
            $bulkRows->push(
                new BulkRow($row->getModel(), $row->getOriginal(), $data->getUniqueBy())
            );
        }

        // Secondly, it needs to fire the 'updatingMany' event for all the rows.
        // If the event dispatcher returns false, none of the rows will be updated.
        if ($models->isNotEmpty()) {
            $this->processAllRows($data, $eventDispatcher, $models, $bulkRows);
        }
    }

    /**
     * Fire the 'updatingMany' event for all the rows.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     * @param \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $bulkRows
     *
     * @return void
     */
    private function processAllRows(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        Collection $models,
        BulkRowCollection $bulkRows,
    ): void
    {
        /** @psalm-suppress MixedAssignment */
        $dispatchingResult = $eventDispatcher->dispatch(BulkEventEnum::UpdatingMany, $models, $bulkRows);

        if ($dispatchingResult === false) {
            foreach ($data->getRows() as $row) {
                $row->setSkipUpdating(true);
            }
        }
    }
}
