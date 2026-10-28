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
 * Dispatches the 'deleting' and the 'restoring' families of events.
 *
 * Both families are handled by the same step on purpose: a row is either being soft deleted
 * or being restored, never both, so a single pass over the rows keeps the events of the
 * neighbouring rows in the very order they were accumulated in.
 *
 * @internal
 */
class DispatchDeletingAndRestoringEventsStep
{
    /**
     * Dispatch the 'deleting', 'deletingMany', 'restoring' and 'restoringMany' events
     * for provided $data->getRows().
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param string|null $deletedAtColumn
     *
     * @return void
     */
    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
    ): void
    {
        if ($deletedAtColumn === null) {
            return;
        }

        $hasDeleteListeners = $eventDispatcher->hasListeners(BulkEventEnum::delete());
        $hasRestoreListeners = $eventDispatcher->hasListeners(BulkEventEnum::restore());

        if (!$hasDeleteListeners && !$hasRestoreListeners) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $deletingModels */
        $deletingModels = $eloquent->newCollection();
        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $restoringModels */
        $restoringModels = $eloquent->newCollection();
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $deletingBulkRows */
        $deletingBulkRows = new BulkRowCollection();
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $restoringBulkRows */
        $restoringBulkRows = new BulkRowCollection();

        // Firstly, it needs to fire the 'deleting' and the 'restoring' events for each row.
        // If the event dispatcher returns false, the corresponding operation is skipped.
        foreach ($data->getRows() as $row) {
            if ($row->isSkipSaving()) {
                continue;
            }

            if ($hasDeleteListeners) {
                $this->processDeletingRow(
                    $row,
                    $data,
                    $eventDispatcher,
                    $deletedAtColumn,
                    $deletingModels,
                    $deletingBulkRows,
                );
            }

            if ($hasRestoreListeners) {
                $this->processRestoringRow(
                    $row,
                    $data,
                    $eventDispatcher,
                    $deletedAtColumn,
                    $restoringModels,
                    $restoringBulkRows,
                );
            }
        }

        // Secondly, it needs to fire the '-Many' events for all the collected rows.
        // If the event dispatcher returns false, the corresponding operation
        // is skipped for every row it was collected for.
        $this->processAllDeletingRows($data, $eventDispatcher, $deletingModels, $deletingBulkRows);
        $this->processAllRestoringRows($data, $eventDispatcher, $restoringModels, $restoringBulkRows);
    }

    /**
     * Fire the 'deleting' event for the row when its $deletedAtColumn has just been filled in.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity $row
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param string $deletedAtColumn
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     * @param \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $bulkRows
     *
     * @return void
     */
    private function processDeletingRow(
        BulkAccumulationItemEntity $row,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        string $deletedAtColumn,
        Collection $models,
        BulkRowCollection $bulkRows,
    ): void
    {
        if ($row->isSkipDeleting()
            || $row->getModel()->getAttribute($deletedAtColumn) === null
            || $row->getModel()->getOriginal($deletedAtColumn) !== null
        ) {
            return;
        }

        $row->setDeleting(true);

        if ($eventDispatcher->dispatch(BulkEventEnum::Deleting, $row->getModel()) === false) {
            $row->setSkipDeleting(true);

            return;
        }

        $models->push($row->getModel());
        $bulkRows->push(
            new BulkRow($row->getModel(), $row->getOriginal(), $data->getUniqueBy())
        );
    }

    /**
     * Fire the 'restoring' event for the row when its $deletedAtColumn has just been cleared.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity $row
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param string $deletedAtColumn
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     * @param \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $bulkRows
     *
     * @return void
     */
    private function processRestoringRow(
        BulkAccumulationItemEntity $row,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        string $deletedAtColumn,
        Collection $models,
        BulkRowCollection $bulkRows,
    ): void
    {
        if ($row->isSkipRestoring()
            || $row->getModel()->getAttribute($deletedAtColumn) !== null
            || $row->getModel()->getOriginal($deletedAtColumn) === null
        ) {
            return;
        }

        $row->setRestoring(true);

        if ($eventDispatcher->dispatch(BulkEventEnum::Restoring, $row->getModel()) === false) {
            $row->setSkipRestoring(true);

            return;
        }

        $models->push($row->getModel());
        $bulkRows->push(
            new BulkRow($row->getModel(), $row->getOriginal(), $data->getUniqueBy())
        );
    }

    /**
     * Fire the 'deletingMany' event for all the collected rows.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     * @param \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $bulkRows
     *
     * @return void
     */
    private function processAllDeletingRows(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        Collection $models,
        BulkRowCollection $bulkRows,
    ): void
    {
        if ($models->isEmpty()) {
            return;
        }

        /** @psalm-suppress MixedAssignment */
        $dispatchingResult = $eventDispatcher->dispatch(BulkEventEnum::DeletingMany, $models, $bulkRows);

        if ($dispatchingResult === false) {
            foreach ($data->getRows() as $row) {
                if ($row->isDeleting()) {
                    $row->setSkipDeleting(true);
                }
            }
        }
    }

    /**
     * Fire the 'restoringMany' event for all the collected rows.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     * @param \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $bulkRows
     *
     * @return void
     */
    private function processAllRestoringRows(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        Collection $models,
        BulkRowCollection $bulkRows,
    ): void
    {
        if ($models->isEmpty()) {
            return;
        }

        /** @psalm-suppress MixedAssignment */
        $dispatchingResult = $eventDispatcher->dispatch(BulkEventEnum::RestoringMany, $models, $bulkRows);

        if ($dispatchingResult === false) {
            foreach ($data->getRows() as $row) {
                if ($row->isRestoring()) {
                    $row->setSkipRestoring(true);
                }
            }
        }
    }
}
