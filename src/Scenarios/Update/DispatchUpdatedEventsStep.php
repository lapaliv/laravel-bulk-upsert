<?php

namespace Lapaliv\BulkUpsert\Scenarios\Update;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Collections\BulkRowCollection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;

/**
 * Dispatches every family of the events ending with '-ed'.
 *
 * All the families are handled by the same step on purpose: they share the single pass
 * over the rows, which keeps the events of one row next to each other
 * in the very order they have always been fired in.
 *
 * @internal
 */
class DispatchUpdatedEventsStep
{
    /**
     * Dispatch the 'updated', 'deleted', 'restored' and 'saved' events
     * (and their '-Many' counterparts) for provided $data->getRows().
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
        $hasSavedListeners = $eventDispatcher->hasListeners(BulkEventEnum::saved());
        $hasUpdatedListeners = $eventDispatcher->hasListeners(BulkEventEnum::updated());
        $hasDeletedListeners = $eventDispatcher->hasListeners(BulkEventEnum::deleted());
        $hasRestoredListeners = $eventDispatcher->hasListeners(BulkEventEnum::restored());

        if (!$hasSavedListeners && !$hasUpdatedListeners && !$hasDeletedListeners && !$hasRestoredListeners) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $savedModels */
        $savedModels = $eloquent->newCollection();
        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $updatedModels */
        $updatedModels = $eloquent->newCollection();
        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $deletedModels */
        $deletedModels = $eloquent->newCollection();
        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $restoredModels */
        $restoredModels = $eloquent->newCollection();
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $savedBulkRows */
        $savedBulkRows = new BulkRowCollection();
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $updatedBulkRows */
        $updatedBulkRows = new BulkRowCollection();
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $deletedBulkRows */
        $deletedBulkRows = new BulkRowCollection();
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $restoredBulkRows */
        $restoredBulkRows = new BulkRowCollection();

        // Firstly, it needs to fire the events for each row that participated in the query.
        foreach ($data->getRows() as $accumulatedRow) {
            if ($accumulatedRow->isSkipSaving()) {
                continue;
            }

            if ($hasUpdatedListeners && !$accumulatedRow->isSkipUpdating()) {
                $eventDispatcher->dispatch(BulkEventEnum::Updated, $accumulatedRow->getModel());
                $this->collect($accumulatedRow, $data, $updatedModels, $updatedBulkRows);
            }

            if ($hasDeletedListeners && $accumulatedRow->isDeleting() && !$accumulatedRow->isSkipDeleting()) {
                $eventDispatcher->dispatch(BulkEventEnum::Deleted, $accumulatedRow->getModel());
                $this->collect($accumulatedRow, $data, $deletedModels, $deletedBulkRows);
            }

            if ($hasRestoredListeners && $accumulatedRow->isRestoring() && !$accumulatedRow->isSkipRestoring()) {
                $eventDispatcher->dispatch(BulkEventEnum::Restored, $accumulatedRow->getModel());
                $this->collect($accumulatedRow, $data, $restoredModels, $restoredBulkRows);
            }

            if ($hasSavedListeners) {
                $eventDispatcher->dispatch(BulkEventEnum::Saved, $accumulatedRow->getModel());
                $this->collect($accumulatedRow, $data, $savedModels, $savedBulkRows);
            }
        }

        // Secondly, it needs to fire the '-Many' events for all the collected rows.
        $this->processAllRows($eventDispatcher, BulkEventEnum::UpdatedMany, $updatedModels, $updatedBulkRows);
        $this->processAllRows($eventDispatcher, BulkEventEnum::DeletedMany, $deletedModels, $deletedBulkRows);
        $this->processAllRows($eventDispatcher, BulkEventEnum::RestoredMany, $restoredModels, $restoredBulkRows);
        $this->processAllRows($eventDispatcher, BulkEventEnum::SavedMany, $savedModels, $savedBulkRows);
    }

    /**
     * Remember the row for the corresponding '-Many' event.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity $row
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     * @param \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $bulkRows
     *
     * @return void
     */
    private function collect(
        BulkAccumulationItemEntity $row,
        BulkAccumulationEntity $data,
        Collection $models,
        BulkRowCollection $bulkRows,
    ): void
    {
        $models->push($row->getModel());
        $bulkRows->push(
            new BulkRow($row->getModel(), $row->getOriginal(), $data->getUniqueBy())
        );
    }

    /**
     * Fire the given '-Many' event for all the collected rows.
     *
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     * @param \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $bulkRows
     *
     * @return void
     */
    private function processAllRows(
        BulkEventDispatcher $eventDispatcher,
        BulkEventEnum $event,
        Collection $models,
        BulkRowCollection $bulkRows,
    ): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $eventDispatcher->dispatch($event, $models, $bulkRows);
    }
}
