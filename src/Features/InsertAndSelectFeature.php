<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;

class InsertAndSelectFeature
{
    public function __construct(
        private GetInsertBuilderFeature $getInsertBuilderFeature,
        private BulkDriverManager $driverManager,
        private SelectExistingRowsFeature $selectExistingRowsFeature,
    ) {
        //
    }

    /**
     * Insert the $data to the database, select rows and return them.
     *
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     * @param bool $ignore
     * @param array $dateFields
     * @param array $selectColumns
     * @param string|null $deletedAtColumn
     *
     * @return array|null
     *
     * @psalm-return null|array{
     *     insertResult: BulkInsertResult,
     *     existingRows: Collection,
     * }
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        bool $ignore,
        array $dateFields,
        array $selectColumns,
        ?string $deletedAtColumn,
    ): ?array {
        if (!$data->hasRows()) {
            return null;
        }

        $eloquent = $data->getFirstModel();
        $needToSelect = $this->doNeedToSelect($eloquent, $data, $eventDispatcher, $deletedAtColumn);

        $builder = $this->getInsertBuilderFeature->handle(
            $data,
            $ignore,
            $dateFields,
            $needToSelect ? $selectColumns : []
        );

        if ($builder === null) {
            return null;
        }

        $driver = $this->driverManager->getForModel($eloquent);

        // In cases when the model does not have any listeners ending with '-ed',
        // the select is an extra operation that may be skipped.
        if (!$needToSelect) {
            $driver->quietInsert($eloquent->getConnection(), $builder);

            return null;
        }

        $insertResult = $driver->insertWithResult(
            $eloquent->getConnection(),
            $builder,
            $eloquent->getIncrementing() ? $eloquent->getKeyName() : null
        );

        return [
            'insertResult' => $insertResult,
            'existingRows' => $this->selectInsertedRows(
                $eloquent,
                $insertResult,
                $data,
                $selectColumns,
                $deletedAtColumn,
            ),
        ];
    }

    /**
     * Return true when the selection may be skipped.
     *
     * @param Model $eloquent
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     * @param string|null $deletedAtColumn
     *
     * @return bool
     */
    private function doNeedToSelect(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
    ): bool {
        $result = $eventDispatcher->hasListeners(BulkEventEnum::saved())
            || $eventDispatcher->hasListeners(BulkEventEnum::created())
            || !empty($eloquent->getTouchedRelations());

        if ($result) {
            return true;
        }

        if ($deletedAtColumn !== null && $eventDispatcher->hasListeners(BulkEventEnum::deleted())) {
            return $data->getModels(
                fn (Model $model) => $model->getAttribute($deletedAtColumn) !== null
            )->isNotEmpty();
        }

        return false;
    }

    private function selectInsertedRows(
        Model $eloquent,
        BulkInsertResult $bulkInsertResult,
        BulkAccumulationEntity $data,
        array $selectColumns,
        ?string $deletedAtColumn,
    ): Collection {
        $insertedRows = $bulkInsertResult->getRows();

        if (is_array($insertedRows)) {
            $models = $eloquent->newCollection();

            foreach ($insertedRows as $row) {
                $model = new $eloquent();
                $model->exists = true;
                $model->wasRecentlyCreated = true;
                $model->setRawAttributes((array) $row);
                $models->push($model);
            }

            return $models;
        }

        return $this->selectExistingRowsFeature->handle(
            $eloquent,
            $data->getModels(),
            $data->getUniqueBy(),
            $selectColumns,
            $deletedAtColumn,
            withTrashed: $deletedAtColumn || $data->getModels(fn (Model $model) => $model->getAttribute($deletedAtColumn) !== null)->isNotEmpty(),
        );
    }
}
