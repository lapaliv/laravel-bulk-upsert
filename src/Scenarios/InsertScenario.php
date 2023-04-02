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
use Lapaliv\BulkUpsert\Features\SelectExistingRowsFeature;
use Lapaliv\BulkUpsert\Scenarios\Insert\InsertScenarioGetBuilderFeature;

/**
 * @internal
 */
class InsertScenario
{
    public function __construct(
        private InsertScenarioGetBuilderFeature $getInsertBuilderFeature,
        private DriverManager $driverManager,
        private SelectExistingRowsFeature $selectExistingRowsFeature,
        private FinishSaveFeature $finishSaveFeature,
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

        if ($eventDispatcher->hasListeners(BulkEventEnum::inserting())) {
            $this->prepareModelsToCreating($eloquent, $data, $eventDispatcher, $deletedAtColumn);
        }

        $this->freshTimestamps($eloquent, $data);

        $startedAt = new DateTime();
        $builder = $this->getInsertBuilderFeature->handle($eloquent, $data, $ignore, $dateFields);

        if ($builder === null) {
            return;
        }

        $driver = $this->driverManager->getForModel($eloquent);
        $lastInsertedId = $driver->insert($eloquent->getConnection(), $builder, $eloquent->getKeyName());
        unset($builder);

        if ($eventDispatcher->hasListeners(BulkEventEnum::inserted()) === false) {
            return;
        }

        $models = $this->selectExistingRowsFeature->handle($eloquent, $data, $selectColumns, $deletedAtColumn);
        $this->prepareModelsToGiving($eloquent, $data, $models, $lastInsertedId, $startedAt);
        unset($models, $startedAt, $lastInsertedId);

        $this->fireInsertedEvents($eloquent, $data, $eventDispatcher, $deletedAtColumn);
        $this->finishSaveFeature->handle($eloquent, $data, $eventDispatcher, $eloquent->getConnection(), $driver);
    }

    private function prepareModelsToCreating(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
    ): void {
        $allModels = $eloquent->newCollection();
        $deletingModels = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $accumulatedRow) {
            if ($eventDispatcher->dispatch(BulkEventEnum::SAVING, $accumulatedRow->model) === false
                || $eventDispatcher->dispatch(BulkEventEnum::CREATING, $accumulatedRow->model) === false
            ) {
                $accumulatedRow->skipped = true;

                continue;
            }

            $allModels->push($accumulatedRow->model);

            if ($deletedAtColumn !== null
                && $accumulatedRow->model->getAttribute($deletedAtColumn) !== null
            ) {
                if ($eventDispatcher->dispatch(BulkEventEnum::DELETING, $accumulatedRow->model) === false) {
                    $accumulatedRow->skipped = true;
                } else {
                    $deletingModels->push($accumulatedRow->model);
                }
            }

            $bulkRows->push(
                new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy)
            );
        }

        if ($allModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::CREATING_MANY, $allModels, $bulkRows);

            if ($deletingModels->isNotEmpty()) {
                $eventDispatcher->dispatch(BulkEventEnum::DELETING_MANY, $deletingModels, $bulkRows);
            }

            $eventDispatcher->dispatch(BulkEventEnum::SAVING_MANY, $allModels, $bulkRows);
        }

        unset($allModels, $deletingModels, $bulkRows);
    }

    private function freshTimestamps(BulkModel $eloquent, BulkAccumulationEntity $data): void
    {
        if ($eloquent->usesTimestamps()) {
            foreach ($data->rows as $accumulatedRow) {
                if ($accumulatedRow->skipped) {
                    continue;
                }

                $accumulatedRow->model->updateTimestamps();
            }
        }
    }

    private function prepareModelsToGiving(
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
                return $this->getUniqueKeyForModel($model, $data->uniqueBy);
            }
        );

        foreach ($data->rows as $row) {
            $key = $this->getUniqueKeyForModel($row->model, $data->uniqueBy);

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

    private function fireInsertedEvents(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
    ): void {
        $allModels = $eloquent->newCollection();
        $deletedModels = $eloquent->newCollection();
        $bulkRows = new BulkRows();

        foreach ($data->rows as $accumulatedRow) {
            if ($accumulatedRow->skipped || $accumulatedRow->model->exists === false) {
                continue;
            }

            $allModels->push($accumulatedRow->model);

            $eventDispatcher->dispatch(BulkEventEnum::CREATED, $accumulatedRow->model);

            if ($deletedAtColumn !== null
                && $accumulatedRow->model->getAttribute($deletedAtColumn) !== null
            ) {
                $deletedModels->push($accumulatedRow->model);
                $eventDispatcher->dispatch(BulkEventEnum::DELETED, $accumulatedRow->model);
            }

            $eventDispatcher->dispatch(BulkEventEnum::SAVED, $accumulatedRow->model);

            $bulkRows->push(
                new BulkRow($accumulatedRow->model, $accumulatedRow->row, $data->uniqueBy, $accumulatedRow->skipped)
            );
        }

        if ($allModels->isNotEmpty()) {
            $eventDispatcher->dispatch(BulkEventEnum::CREATED_MANY, $allModels, $bulkRows);

            if ($deletedModels->isNotEmpty()) {
                $eventDispatcher->dispatch(BulkEventEnum::DELETED_MANY, $allModels, $bulkRows);
            }

            $eventDispatcher->dispatch(BulkEventEnum::SAVED_MANY, $allModels, $bulkRows);
        }
    }

    private function getUniqueKeyForModel(BulkModel $model, array $unique): string
    {
        $key = '';

        foreach ($unique as $attribute) {
            $key .= ':' . ($model->getAttribute($attribute) ?? '');
        }

        return hash('crc32c', $key);
    }
}
