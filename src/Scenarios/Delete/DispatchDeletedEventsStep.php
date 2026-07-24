<?php

namespace Lapaliv\BulkUpsert\Scenarios\Delete;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Collections\BulkRowCollection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;

/**
 * Dispatches the 'deleted' families of events after the rows have been deleted.
 *
 * @internal
 */
class DispatchDeletedEventsStep
{
    /**
     * Dispatch the 'deleted' and 'deletedMany' events for provided $data->getRows().
     *
     * When the rows have been force deleted, the 'forceDeleted' and 'forceDeletedMany'
     * events are dispatched as well.
     *
     * The query does not bring the models back from the database, so the models are
     * given the $deletedAt value the query has written, and only then do the listeners see them.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param string|null $deletedAtColumn
     * @param bool $force
     * @param \DateTimeInterface $deletedAt
     *
     * @return void
     */
    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
        bool $force,
        DateTimeInterface $deletedAt,
    ): void
    {
        if (!$eventDispatcher->hasListeners(BulkEventEnum::deleted($force))) {
            return;
        }

        $isForcing = $deletedAtColumn !== null && $force;

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $collection */
        $collection = $eloquent->newCollection();
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $bulkRows */
        $bulkRows = new BulkRowCollection();

        // Firstly, it needs to fire the 'deleted' event for each deleted row.
        foreach ($data->getRows() as $row) {
            if ($row->isSkipDeleting()) {
                continue;
            }

            if ($deletedAtColumn !== null) {
                $row->getModel()->setAttribute($deletedAtColumn, $deletedAt);
            }

            $eventDispatcher->dispatch(BulkEventEnum::Deleted, $row->getModel());

            if ($isForcing) {
                $eventDispatcher->dispatch(BulkEventEnum::ForceDeleted, $row->getModel());
            }

            $collection->push($row->getModel());
            $bulkRows->push(
                new BulkRow($row->getModel(), $row->getOriginal(), $data->getUniqueBy())
            );
        }

        // Secondly, it needs to fire the 'deletedMany' event for all the rows.
        $eventDispatcher->dispatch(BulkEventEnum::DeletedMany, $collection, $bulkRows);

        if ($isForcing) {
            $eventDispatcher->dispatch(BulkEventEnum::ForceDeletedMany, $collection, $bulkRows);
        }

        unset($collection, $bulkRows);
    }
}
