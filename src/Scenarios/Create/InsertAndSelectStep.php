<?php

namespace Lapaliv\BulkUpsert\Scenarios\Create;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Builders\InsertBuilderFactory;
use Lapaliv\BulkUpsert\Builders\SelectExistingRowsQuery;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Contracts\BulkInsertedIndexes;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;

/**
 * Inserts the rows into the database and selects them back when it is needed.
 *
 * @internal
 */
readonly class InsertAndSelectStep
{
    /**
     * Create a new step instance.
     *
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilderFactory $insertBuilderFactory
     * @param \Lapaliv\BulkUpsert\Contracts\BulkDriverManager $driverManager
     * @param \Lapaliv\BulkUpsert\Builders\SelectExistingRowsQuery $selectExistingRowsQuery
     */
    public function __construct(
        private InsertBuilderFactory $insertBuilderFactory,
        private BulkDriverManager $driverManager,
        private SelectExistingRowsQuery $selectExistingRowsQuery,
    )
    {
        //
    }

    /**
     * Insert the $data into the database, then retrieve and return the selected rows.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param bool $ignore
     * @param string[] $dateFields
     * @param string[] $selectColumns
     * @param string|null $deletedAtColumn
     *
     * @param bool $returnModels Whether the rows have to be read back to be given back.
     * @param bool $duplicatesOnly Whether `$ignore` may skip a duplicate key only, not other errors.
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
        bool $returnModels = false,
        bool $duplicatesOnly = false,
    ): ?array
    {
        if (!$data->hasRows()) {
            return null;
        }

        // The listeners have run: the rows which are still to be inserted must still be bound
        // to the table and the connection of the bulk, the first one gives the statement.
        /** @var \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $pending */
        $pending = $data->getModels();
        $data->assertTarget($pending);

        $eloquent = $data->getFirstModel();
        // The models which are given back need their keys, so they are read back as well.
        $needToSelect = $returnModels || $this->doNeedToSelect($eloquent, $data, $eventDispatcher, $deletedAtColumn);

        $builder = $this->insertBuilderFactory->handle(
            $data,
            $ignore,
            $dateFields,
            $needToSelect ? $selectColumns : [],
            $duplicatesOnly,
        );

        if ($builder === null) {
            return null;
        }

        $driver = $this->driverManager->getForModel($eloquent);

        // In cases when the model does not have any listeners ending with '-ed',
        // the select is an extra operation that may be skipped.
        if (!$needToSelect) {
            $driver->quietInsert($eloquent->getConnection(), $builder);
            $this->markWritten($data);

            return null;
        }

        // The values of the statement follow the rows of the data in their order.
        $rows = array_values($data->getRows());

        foreach ($rows as $row) {
            $row->setCreatedByInsert(null);
        }

        $insertResult = $driver->insertWithResult(
            $eloquent->getConnection(),
            $builder,
            $eloquent->getIncrementing() ? $eloquent->getKeyName() : null
        );
        // The rows are written now: a failure below must not send them to the database again.
        $this->markWritten($data);

        // A driver which knows which rows the statement has created says it exactly, so a row
        // which somebody else has inserted meanwhile is not taken as created by this call.
        $insertedIndexes = $insertResult instanceof BulkInsertedIndexes
            ? $insertResult->getInsertedIndexes()
            : null;

        if ($insertedIndexes !== null) {
            $inserted = array_flip($insertedIndexes);

            foreach ($rows as $index => $row) {
                $row->setCreatedByInsert(isset($inserted[$index]));
            }
        }

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
     * Return true when the inserted rows have to be selected back from the database.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param string|null $deletedAtColumn
     *
     * @return bool
     */
    private function doNeedToSelect(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ?string $deletedAtColumn,
    ): bool
    {
        $result = $eventDispatcher->hasListeners(BulkEventEnum::saved())
            || $eventDispatcher->hasListeners(BulkEventEnum::created())
            || !empty($eloquent->getTouchedRelations());

        if ($result) {
            return true;
        }

        if ($deletedAtColumn !== null && $eventDispatcher->hasListeners(BulkEventEnum::deleted())) {
            return $data->getModels(
                fn(Model $model) => $model->getAttribute($deletedAtColumn) !== null
            )->isNotEmpty();
        }

        return false;
    }

    /**
     * Return the inserted rows, either from the insert result or by selecting them back.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Contracts\BulkInsertResult $bulkInsertResult
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param string[] $selectColumns
     * @param string|null $deletedAtColumn
     *
     * @return \Illuminate\Support\Collection
     */
    private function selectInsertedRows(
        Model $eloquent,
        BulkInsertResult $bulkInsertResult,
        BulkAccumulationEntity $data,
        array $selectColumns,
        ?string $deletedAtColumn,
    ): Collection
    {
        $insertedRows = $bulkInsertResult->getRows();

        if (is_array($insertedRows)) {
            $models = $eloquent->newCollection();

            /** @psalm-suppress MixedAssignment */
            foreach ($insertedRows as $row) {
                $model = $eloquent->newInstance([], true);
                $model->wasRecentlyCreated = true;
                $model->setRawAttributes((array)$row);
                $models->push($model);
            }

            return $models;
        }

        return $this->selectExistingRowsQuery->handle(
            $eloquent,
            $data->getModels(),
            $data->getUniqueBy(),
            $selectColumns,
            $deletedAtColumn,
            withTrashed: $deletedAtColumn !== null,
        );
    }

    /**
     * Remember that the statement has written every row of the data.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     *
     * @return void
     */
    private function markWritten(BulkAccumulationEntity $data): void
    {
        foreach ($data->getRows() as $row) {
            $row->markWritten();
        }
    }
}
