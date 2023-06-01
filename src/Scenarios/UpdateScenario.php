<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Collections\BulkRows;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Features\GetUpdateBuilderFeature;
use Lapaliv\BulkUpsert\Features\MarkNonexistentRowsAsSkippedFeature;
use Lapaliv\BulkUpsert\Features\TouchRelationsFeature;

/**
 * @internal
 */
class UpdateScenario
{
    public function __construct(
        private MarkNonexistentRowsAsSkippedFeature $markNonexistentRowsAsSkipped,
        private GetUpdateBuilderFeature $getUpdateBuilderFeature,
        private BulkDriverManager $driverManager,
        private TouchRelationsFeature $touchRelationsFeature,
    ) {
        //
    }

    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        array $dateFields,
        array $selectColumns,
        ?string $deletedAtColumn,
    ): void {
        if (empty($data->rows)) {
            return;
        }

        $this->markNonexistentRowsAsSkipped->handle($eloquent, $data, $selectColumns, $deletedAtColumn);

        if ($eventDispatcher->hasListeners(BulkEventEnum::saving())
            || $eventDispatcher->hasListeners(BulkEventEnum::updating())
            || $eventDispatcher->hasListeners(BulkEventEnum::delete())
            || $eventDispatcher->hasListeners(BulkEventEnum::restore())
        ) {
            $this->dispatchSavingEvents($eloquent, $data, $eventDispatcher);
            $this->dispatchUpdatingEvents($eloquent, $data, $eventDispatcher);

            if ($deletedAtColumn !== null) {
                $this->dispatchDeletingAndRestoringEvents($eloquent, $data, $eventDispatcher, $deletedAtColumn);
            }
        }

        $builder = $this->getUpdateBuilderFeature->handle($eloquent, $data, $dateFields, $deletedAtColumn);
        $driver = $this->driverManager->getForModel($eloquent);

        if ($builder !== null) {
            $driver->update($eloquent->getConnection(), $builder);
        }

        unset($builder);

        $hasEndListeners = $eventDispatcher->hasListeners(BulkEventEnum::saved())
            || $eventDispatcher->hasListeners(BulkEventEnum::updated())
            || $eventDispatcher->hasListeners(BulkEventEnum::deleted())
            || $eventDispatcher->hasListeners(BulkEventEnum::restored());

        if ($hasEndListeners) {
            $this->fireUpdatedEvents($eloquent, $data, $eventDispatcher);
            $this->syncChanges($data);
        }

        if (!empty($eloquent->getTouchedRelations())) {
            $this->touchRelationsFeature->handle($eloquent, $data, $eventDispatcher, $eloquent->getConnection(), $driver);
        }

        unset($driver);

        if ($hasEndListeners) {
            $this->syncOriginal($data);
        }
    }

    private function dispatchSavingEvents(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        if (!$eventDispatcher->hasListeners(BulkEventEnum::saving())) {
            return;
        }

        $models = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $row) {
            if ($eventDispatcher->dispatch(BulkEventEnum::SAVING, $row->model) === false) {
                $row->skipSaving = true;

                continue;
            }

            if (!$row->model->isDirty()) {
                $row->skipUpdating = true;
            }

            $models->push($row->model);
            $bulkRows->push(
                new BulkRow($row->model, $row->row, $data->uniqueBy)
            );
        }

        if ($models->isNotEmpty()) {
            $eventResult = $eventDispatcher->dispatch(BulkEventEnum::SAVING_MANY, $models, $bulkRows);

            if ($eventResult === false) {
                foreach ($data->rows as $row) {
                    $row->skipSaving = true;
                }
            }
        }

        unset($models, $bulkRows);
    }

    private function dispatchUpdatingEvents(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        if (!$eventDispatcher->hasListeners(BulkEventEnum::updating())) {
            return;
        }

        $models = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $row) {
            if ($row->skipSaving || $row->skipUpdating) {
                continue;
            }

            if ($eventDispatcher->dispatch(BulkEventEnum::UPDATING, $row->model) === false) {
                $row->skipUpdating = true;

                continue;
            }

            $models->push($row->model);
            $bulkRows->push(
                new BulkRow($row->model, $row->row, $data->uniqueBy)
            );
        }

        if ($models->isNotEmpty()) {
            $eventResult = $eventDispatcher->dispatch(BulkEventEnum::UPDATING_MANY, $models, $bulkRows);

            if ($eventResult === false) {
                foreach ($data->rows as $row) {
                    $row->skipUpdating = true;
                }
            }
        }

        unset($models, $bulkRows);
    }

    private function dispatchDeletingAndRestoringEvents(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        string $deletedAtColumn,
    ): void {
        $hasDeleteListeners = $eventDispatcher->hasListeners(BulkEventEnum::delete());
        $hasRestoreListeners = $eventDispatcher->hasListeners(BulkEventEnum::restore());

        if (!$hasDeleteListeners && !$hasRestoreListeners) {
            return;
        }

        $deletingModels = $eloquent->newCollection();
        $restoringModels = $eloquent->newCollection();
        $deletingBulkRows = new BulkRows();
        $restoringBulkRows = new BulkRows();

        foreach ($data->rows as $row) {
            if ($row->skipSaving) {
                continue;
            }

            if ($hasDeleteListeners
                && !$row->skipDeleting
                && $row->model->getAttribute($deletedAtColumn) !== null
                && $row->model->getOriginal($deletedAtColumn) === null
            ) {
                $row->isDeleting = true;

                if ($eventDispatcher->dispatch(BulkEventEnum::DELETING, $row->model) === false) {
                    $row->skipDeleting = true;
                } else {
                    $deletingModels->push($row->model);
                    $deletingBulkRows->push(
                        new BulkRow($row->model, $row->row, $data->uniqueBy)
                    );
                }
            }

            if ($hasRestoreListeners
                && !$row->skipRestoring
                && $row->model->getAttribute($deletedAtColumn) === null
                && $row->model->getOriginal($deletedAtColumn) !== null
            ) {
                $row->isRestoring = true;

                if ($eventDispatcher->dispatch(BulkEventEnum::RESTORING, $row->model) === false) {
                    $row->skipRestoring = true;
                } else {
                    $restoringModels->push($row->model);
                    $restoringBulkRows->push(
                        new BulkRow($row->model, $row->row, $data->uniqueBy)
                    );
                }
            }
        }

        if ($deletingModels->isNotEmpty()) {
            $eventResult = $eventDispatcher->dispatch(BulkEventEnum::DELETING_MANY, $deletingModels, $deletingBulkRows);

            if ($eventResult === false) {
                foreach ($data->rows as $row) {
                    if ($row->isDeleting) {
                        $row->skipDeleting = true;
                    }
                }
            }
        }

        if ($restoringModels->isNotEmpty()) {
            $eventResult = $eventDispatcher->dispatch(BulkEventEnum::RESTORING_MANY, $restoringModels, $restoringBulkRows);

            if ($eventResult === false) {
                foreach ($data->rows as $row) {
                    if ($row->isRestoring) {
                        $row->skipRestoring = true;
                    }
                }
            }
        }

        unset($deletingModels, $deletingBulkRows, $restoringModels, $restoringBulkRows, $eventResult);
    }

    private function fireUpdatedEvents(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        $hasSavedListeners = $eventDispatcher->hasListeners(BulkEventEnum::saved());
        $hasUpdatedListeners = $eventDispatcher->hasListeners(BulkEventEnum::updated());
        $hasDeletedListeners = $eventDispatcher->hasListeners(BulkEventEnum::deleted());
        $hasRestoredListeners = $eventDispatcher->hasListeners(BulkEventEnum::restored());

        if (!$hasSavedListeners && !$hasUpdatedListeners && !$hasDeletedListeners && !$hasRestoredListeners) {
            return;
        }

        $savedModels = $eloquent->newCollection();
        $updatedModels = $eloquent->newCollection();
        $deletedModels = $eloquent->newCollection();
        $restoredModels = $eloquent->newCollection();
        $savedBulkRows = new BulkRows();
        $updatedBulkRows = new BulkRows();
        $deletedBulkRows = new BulkRows();
        $restoredBulkRows = new BulkRows();

        foreach ($data->rows as $accumulatedRow) {
            if ($accumulatedRow->skipSaving) {
                continue;
            }

            if ($hasUpdatedListeners && !$accumulatedRow->skipUpdating) {
                $eventDispatcher->dispatch(BulkEventEnum::UPDATED, $accumulatedRow->model);
                $updatedModels->push($accumulatedRow->model);
                $updatedBulkRows->push(
                    new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                );
            }

            if ($hasDeletedListeners && $accumulatedRow->isDeleting && !$accumulatedRow->skipDeleting) {
                $eventDispatcher->dispatch(BulkEventEnum::DELETED, $accumulatedRow->model);
                $deletedModels->push($accumulatedRow->model);
                $deletedBulkRows->push(
                    new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                );
            }

            if ($hasRestoredListeners && $accumulatedRow->isRestoring && !$accumulatedRow->skipRestoring) {
                $eventDispatcher->dispatch(BulkEventEnum::RESTORED, $accumulatedRow->model);
                $restoredModels->push($accumulatedRow->model);
                $restoredBulkRows->push(
                    new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                );
            }

            if ($hasSavedListeners) {
                $eventDispatcher->dispatch(BulkEventEnum::SAVED, $accumulatedRow->model);
                $savedModels->push($accumulatedRow->model);
                $savedBulkRows->push(
                    new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                );
            }
        }

        unset($hasSavedListeners, $hasUpdatedListeners, $hasDeletedListeners, $hasRestoredListeners);

        if ($updatedModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::UPDATED_MANY, $updatedModels, $updatedBulkRows);
        }

        if ($deletedModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::DELETED_MANY, $deletedModels, $deletedBulkRows);
        }

        if ($restoredModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::RESTORED_MANY, $restoredModels, $restoredBulkRows);
        }

        if ($savedModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::SAVED_MANY, $savedModels, $savedBulkRows);
        }

        unset(
            $savedBulkRows, $updatedBulkRows, $deletedBulkRows, $restoredBulkRows,
            $savedModels, $updatedModels, $deletedModels, $restoredModels,
        );
    }

    private function syncChanges(BulkAccumulationEntity $data): void
    {
        foreach ($data->rows as $row) {
            if ($row->skipSaving) {
                continue;
            }

            $row->model->syncChanges();
        }
    }

    private function syncOriginal(BulkAccumulationEntity $data): void
    {
        foreach ($data->rows as $row) {
            if ($row->skipSaving) {
                continue;
            }

            $row->model->syncOriginal();
        }
    }
}
