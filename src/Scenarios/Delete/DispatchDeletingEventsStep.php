<?php

namespace Lapaliv\BulkUpsert\Scenarios\Delete;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Collections\BulkRowCollection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;

/**
 * Dispatches the 'deleting' families of events before the rows are deleted.
 *
 * @internal
 */
class DispatchDeletingEventsStep
{
    /**
     * Dispatch the 'deleting' and 'deletingMany' events for provided $data->getRows().
     *
     * When the rows are being force deleted, the 'forceDeleting' and 'forceDeletingMany'
     * events are dispatched as well. Note that the force events only make sense for the models
     * that support soft deleting: there is nothing to force when $deletedAtColumn is null.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param string|null $deletedAtColumn
     * @param bool $force
     *
     * @return void
     */
    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
        bool $force,
    ): void
    {
        if (!$eventDispatcher->hasListeners(BulkEventEnum::delete($force))) {
            return;
        }

        $isForcing = $deletedAtColumn !== null && $force;

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $collection */
        $collection = $eloquent->newCollection();
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $bulkRows */
        $bulkRows = new BulkRowCollection();

        // Firstly, it needs to fire the 'deleting' event for each row.
        // If the event dispatcher returns false, the row won't be deleted.
        foreach ($data->getRows() as $row) {
            if ($row->isSkipDeleting()) {
                continue;
            }

            if ($isForcing
                && $eventDispatcher->dispatch(BulkEventEnum::ForceDeleting, $row->getModel()) === false
            ) {
                $row->setSkipDeleting(true);

                continue;
            }

            if ($eventDispatcher->dispatch(BulkEventEnum::Deleting, $row->getModel()) === false) {
                $row->setSkipDeleting(true);

                continue;
            }

            $collection->push($row->getModel());
            $bulkRows->push(
                new BulkRow($row->getModel(), $row->getOriginal(), $data->getUniqueBy())
            );
        }

        // Secondly, it needs to fire the 'deletingMany' event for all the rows.
        // If the event dispatcher returns false, none of the rows will be deleted.
        $this->processAllRows($data, $eventDispatcher, $collection, $bulkRows, $isForcing);

        unset($collection, $bulkRows);
    }

    /**
     * Fire the 'forceDeletingMany' and 'deletingMany' events for all the rows.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $collection
     * @param \Lapaliv\BulkUpsert\Collections\BulkRowCollection $bulkRows
     * @param bool $isForcing
     *
     * @return void
     */
    private function processAllRows(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        Collection $collection,
        BulkRowCollection $bulkRows,
        bool $isForcing,
    ): void
    {
        if ($collection->isEmpty()) {
            return;
        }

        if ($isForcing
            && $eventDispatcher->dispatch(BulkEventEnum::ForceDeletingMany, $collection, $bulkRows) === false
        ) {
            $this->skipAllRows($data);

            return;
        }

        if ($eventDispatcher->dispatch(BulkEventEnum::DeletingMany, $collection, $bulkRows) === false) {
            $this->skipAllRows($data);
        }
    }

    /**
     * Mark every row as the one that should not be deleted.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     *
     * @return void
     */
    private function skipAllRows(BulkAccumulationEntity $data): void
    {
        foreach ($data->getRows() as $row) {
            $row->setSkipDeleting(true);
        }
    }
}
