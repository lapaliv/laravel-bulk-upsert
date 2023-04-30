<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use DateTime;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Collection\BulkRows;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\DriverManager;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Features\FinishSaveFeature;
use Lapaliv\BulkUpsert\Features\GetInsertBuilderFeature;
use Lapaliv\BulkUpsert\Features\GetUniqueKeyFeature;
use Lapaliv\BulkUpsert\Features\SelectExistingRowsFeature;

/**
 * @internal
 */
class CreateScenario
{
    public function __construct(
        private GetInsertBuilderFeature $getInsertBuilderFeature,
        private DriverManager $driverManager,
        private SelectExistingRowsFeature $selectExistingRowsFeature,
        private FinishSaveFeature $finishSaveFeature,
        private GetUniqueKeyFeature $getUniqueKeyFeature,
    ) {
        //
    }

    public function handle(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        bool $ignore,
        array $dateFields,
        array $selectColumns,
        ?string $deletedAtColumn,
    ): void {
        if (empty($data->rows)) {
            return;
        }

        if ($eventDispatcher->hasListeners(BulkEventEnum::saving())
            || $eventDispatcher->hasListeners(BulkEventEnum::creating())
            || $eventDispatcher->hasListeners(BulkEventEnum::delete())
        ) {
            $this->dispatchSavingEvents($eloquent, $data, $eventDispatcher);
            $this->dispatchCreatingEvents($eloquent, $data, $eventDispatcher);

            if ($deletedAtColumn !== null) {
                $this->dispatchDeletingEvents($eloquent, $data, $eventDispatcher, $deletedAtColumn);
            }
        }

        $this->freshTimestamps($eloquent, $data);

        $startedAt = new DateTime();
        $builder = $this->getInsertBuilderFeature->handle($eloquent, $data, $ignore, $dateFields, $deletedAtColumn);

        if ($builder === null) {
            unset($builder, $startedAt);

            return;
        }

        $driver = $this->driverManager->getForModel($eloquent);
        $lastInsertedId = $driver->insert($eloquent->getConnection(), $builder, $eloquent->getKeyName());
        unset($builder);

        if ($eventDispatcher->hasListeners(BulkEventEnum::saved()) === false
            && $eventDispatcher->hasListeners(BulkEventEnum::created()) === false
            && $eventDispatcher->hasListeners(BulkEventEnum::deleted()) === false
        ) {
            unset($driver, $lastInsertedId, $startedAt);

            return;
        }

        $models = $this->selectExistingRowsFeature->handle(
            $eloquent,
            $data->getNotSkippedModels('skipSaving'),
            $data->uniqueBy,
            $selectColumns,
            $deletedAtColumn
        );
        $this->prepareModelsForGiving($eloquent, $data, $models, $lastInsertedId, $startedAt);
        unset($models, $startedAt, $lastInsertedId);

        $this->fireCreatedEvents($eloquent, $data, $eventDispatcher);
        $this->finishSaveFeature->handle($eloquent, $data, $eventDispatcher, $eloquent->getConnection(), $driver);

        unset($driver);
    }

    private function dispatchSavingEvents(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        if ($eventDispatcher->hasListeners(BulkEventEnum::saving()) === false) {
            return;
        }

        $models = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $accumulatedRow) {
            if ($eventDispatcher->dispatch(BulkEventEnum::SAVING, $accumulatedRow->model) === false) {
                $accumulatedRow->skipSaving = true;

                continue;
            }

            $models->push($accumulatedRow->model);
            $bulkRows->push(
                new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
            );
        }

        if ($models->isNotEmpty()
            && $eventDispatcher->dispatch(BulkEventEnum::SAVING_MANY, $models, $bulkRows) === false
        ) {
            foreach ($data->rows as $row) {
                $row->skipSaving = true;
            }
        }

        unset($models, $bulkRows);
    }

    private function dispatchCreatingEvents(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        if ($eventDispatcher->hasListeners(BulkEventEnum::creating()) === false) {
            return;
        }

        $models = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $accumulatedRow) {
            if ($accumulatedRow->skipSaving) {
                continue;
            }

            if ($accumulatedRow->model->exists) {
                $accumulatedRow->skipCreating = true;

                continue;
            }

            if ($eventDispatcher->dispatch(BulkEventEnum::CREATING, $accumulatedRow->model) === false) {
                $accumulatedRow->skipCreating = true;

                continue;
            }

            $models->push($accumulatedRow->model);
            $bulkRows->push(
                new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
            );
        }

        if ($models->isNotEmpty()
            && $eventDispatcher->dispatch(BulkEventEnum::CREATING_MANY, $models, $bulkRows) === false
        ) {
            foreach ($data->rows as $row) {
                $row->skipCreating = true;
            }
        }

        unset($models, $bulkRows);
    }

    private function dispatchDeletingEvents(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        string $deletedAtColumn
    ): void {
        if ($eventDispatcher->hasListeners(BulkEventEnum::delete()) === false) {
            return;
        }

        $models = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $accumulatedRow) {
            if ($accumulatedRow->skipSaving || $accumulatedRow->skipCreating) {
                continue;
            }

            if ($accumulatedRow->model->getAttribute($deletedAtColumn) === null) {
                continue;
            }

            $accumulatedRow->isDeleting = true;

            if ($eventDispatcher->dispatch(BulkEventEnum::DELETING, $accumulatedRow->model) === false) {
                $accumulatedRow->skipDeleting = true;

                continue;
            }

            $models->push($accumulatedRow->model);
            $bulkRows->push(
                new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
            );
        }

        if ($models->isNotEmpty()
            && $eventDispatcher->dispatch(BulkEventEnum::DELETING_MANY, $models, $bulkRows) === false
        ) {
            foreach ($data->rows as $row) {
                if ($row->isDeleting) {
                    $row->skipDeleting = true;
                }
            }
        }

        unset($models, $bulkRows);
    }

    private function freshTimestamps(BulkModel $eloquent, BulkAccumulationEntity $data): void
    {
        if ($eloquent->usesTimestamps()) {
            foreach ($data->rows as $accumulatedRow) {
                if ($accumulatedRow->skipCreating) {
                    continue;
                }

                $accumulatedRow->model->updateTimestamps();
            }
        }
    }

    private function prepareModelsForGiving(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        Collection $existingRows,
        ?int $lastInsertedId,
        DateTimeInterface $startedAt,
    ): void {
        $hasIncrementing = $eloquent->getIncrementing();
        $createdAtColumn = $eloquent->usesTimestamps()
            ? $eloquent->getCreatedAtColumn()
            : null;
        $keyedExistingRows = $existingRows->keyBy(
            function (BulkModel $model) use ($data): string {
                return $this->getUniqueKeyFeature->handle($model, $data->uniqueBy);
            }
        );

        foreach ($data->rows as $row) {
            if ($row->skipSaving || $row->skipCreating) {
                continue;
            }

            $key = $this->getUniqueKeyFeature->handle($row->model, $data->uniqueBy);

            if ($keyedExistingRows->has($key)) {
                $row->model = $keyedExistingRows->get($key);

                if ($hasIncrementing) {
                    $row->model->wasRecentlyCreated = $row->model->getKey() > $lastInsertedId;
                } elseif ($createdAtColumn !== null
                    && $row->model->getAttribute($createdAtColumn) instanceof DateTimeInterface
                ) {
                    $row->model->wasRecentlyCreated = $startedAt < $row->model->getAttribute($createdAtColumn);
                }
            }
        }
    }

    private function fireCreatedEvents(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void {
        $hasSavedListeners = $eventDispatcher->hasListeners(BulkEventEnum::saved());
        $hasCreatedListeners = $eventDispatcher->hasListeners(BulkEventEnum::created());
        $hasDeletedListeners = $eventDispatcher->hasListeners(BulkEventEnum::deleted());

        $savedModels = $eloquent->newCollection();
        $createdModels = $eloquent->newCollection();
        $deletedModels = $eloquent->newCollection();
        $savedBulkRows = new BulkRows();
        $createdBulkRows = new BulkRows();
        $deletedBulkRows = new BulkRows();

        foreach ($data->rows as $accumulatedRow) {
            if ($accumulatedRow->skipSaving) {
                continue;
            }

            if ($accumulatedRow->skipCreating === false && $accumulatedRow->model->exists) {
                if ($hasCreatedListeners) {
                    $createdModels->push($accumulatedRow->model);
                    $createdBulkRows->push(
                        new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                    );
                    $eventDispatcher->dispatch(BulkEventEnum::CREATED, $accumulatedRow->model);
                }

                if ($hasDeletedListeners && $accumulatedRow->isDeleting && $accumulatedRow->skipDeleting === false) {
                    $deletedModels->push($accumulatedRow->model);
                    $deletedBulkRows->push(
                        new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                    );
                    $eventDispatcher->dispatch(BulkEventEnum::DELETED, $accumulatedRow->model);
                }
            }

            if ($hasSavedListeners) {
                $savedModels->push($accumulatedRow->model);
                $savedBulkRows->push(
                    new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
                );
                $eventDispatcher->dispatch(BulkEventEnum::SAVED, $accumulatedRow->model);
            }
        }

        if ($createdModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::CREATED_MANY, $createdModels, $createdBulkRows);
        }

        unset($createdModels, $createdBulkRows);

        if ($deletedModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::DELETED_MANY, $deletedModels, $deletedBulkRows);
        }

        unset($deletedBulkRows, $deletedModels);

        if ($savedModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::SAVED_MANY, $savedModels, $savedBulkRows);
        }

        unset($savedBulkRows, $savedModels);
    }
}
