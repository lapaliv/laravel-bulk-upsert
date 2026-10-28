<?php

namespace Lapaliv\BulkUpsert\Scenarios\Create;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Collections\BulkRowCollection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;

/**
 * Dispatches every family of the events ending with '-ed'.
 *
 * All the families share the single pass over the rows, so a row gets its 'created',
 * 'deleted' and 'saved' events one after another, and the '-Many' events follow when
 * every row has been visited. This mirrors the updating scenario and keeps the work
 * down to one loop instead of one loop per family.
 *
 * @internal
 */
class DispatchCreatedEventsStep
{
    /**
     * Dispatch the 'created', 'deleted' and 'saved' events, and their '-Many' counterparts.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param string|null $deletedAtColumn
     *
     * @return void
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
    ): void
    {
        if (!$data->hasRows()) {
            return;
        }

        $hasCreatedListeners = $eventDispatcher->hasListeners(BulkEventEnum::created());
        $hasSavedListeners = $eventDispatcher->hasListeners(BulkEventEnum::saved());
        $hasDeletedListeners = $deletedAtColumn !== null
            && $eventDispatcher->hasListeners(BulkEventEnum::deleted());

        if (!$hasCreatedListeners && !$hasDeletedListeners && !$hasSavedListeners) {
            return;
        }

        $eloquent = $data->getFirstModel();

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $createdModels */
        $createdModels = $eloquent->newCollection();
        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $deletedModels */
        $deletedModels = $eloquent->newCollection();
        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $savedModels */
        $savedModels = $eloquent->newCollection();
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $createdBulkRows */
        $createdBulkRows = new BulkRowCollection();
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $deletedBulkRows */
        $deletedBulkRows = new BulkRowCollection();
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $savedBulkRows */
        $savedBulkRows = new BulkRowCollection();

        // Firstly, it needs to fire the events for each row that took part in the query.
        foreach ($data->getRows() as $row) {
            $model = $row->getModel();

            // The 'created' event must be fired only for the models which have the property
            // $wasRecentlyCreated set to true. A model which had been stored before does not
            // need this event.
            if ($hasCreatedListeners && $model->wasRecentlyCreated) {
                $eventDispatcher->dispatch(BulkEventEnum::Created, $model);
                $this->collect($row, $data, $createdModels, $createdBulkRows);
            }

            // The 'deleted' event must be fired only for the rows which have a filled
            // $deletedAtColumn and have either been created just now or carry a change
            // of that very column.
            if ($hasDeletedListeners
                && $model->getAttribute($deletedAtColumn) !== null
                && ($model->wasRecentlyCreated || $model->isDirty($deletedAtColumn))
            ) {
                $eventDispatcher->dispatch(BulkEventEnum::Deleted, $model);
                $this->collect($row, $data, $deletedModels, $deletedBulkRows);
            }

            // The 'saved' event is fired anyway for all the accumulated rows. At this point
            // `$data->getRows()` holds only the rows which took part in the query.
            if ($hasSavedListeners) {
                $eventDispatcher->dispatch(BulkEventEnum::Saved, $model);
                $this->collect($row, $data, $savedModels, $savedBulkRows);
            }
        }

        unset($hasCreatedListeners, $hasDeletedListeners, $hasSavedListeners);

        // Secondly, it needs to fire the '-Many' events for all the collected rows.
        $this->processAllRows($eventDispatcher, BulkEventEnum::CreatedMany, $createdModels, $createdBulkRows);
        $this->processAllRows($eventDispatcher, BulkEventEnum::DeletedMany, $deletedModels, $deletedBulkRows);
        $this->processAllRows($eventDispatcher, BulkEventEnum::SavedMany, $savedModels, $savedBulkRows);

        unset(
            $createdModels, $deletedModels, $savedModels,
            $createdBulkRows, $deletedBulkRows, $savedBulkRows,
        );
    }

    /**
     * Remember the row for the corresponding '-Many' event.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity $row
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     * @param \Lapaliv\BulkUpsert\Collections\BulkRowCollection $bulkRows
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
     * @param \Lapaliv\BulkUpsert\Collections\BulkRowCollection $bulkRows
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
