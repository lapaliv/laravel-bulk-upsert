<?php

namespace Lapaliv\BulkUpsert\Builders;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Support\WriteKeyResolver;

/**
 * Builds either a DELETE statement (force delete) or an UPDATE statement
 * that fills in the soft-delete column, depending on the model.
 *
 * @internal
 */
readonly class DeleteBuilderFactory
{
    /**
     * Create a new factory instance.
     *
     * @param \Lapaliv\BulkUpsert\Builders\WhereClauseApplier $whereClauseApplier
     * @param \Lapaliv\BulkUpsert\Support\WriteKeyResolver $writeKeyResolver
     */
    public function __construct(
        private WhereClauseApplier $whereClauseApplier,
        private WriteKeyResolver $writeKeyResolver,
    )
    {
        //
    }

    /**
     * Build the statement which removes the given rows.
     *
     * The rows are deleted for real when the model does not use soft deletes
     * or when the deletion is forced. Otherwise the soft-delete column is filled in
     * by an UPDATE statement.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param array $dateFields The date format of each date column.
     * @param string|null $deletedAtColumn
     * @param bool $force
     * @param \DateTimeInterface $deletedAt
     *
     * @return \Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder|\Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder|null
     */
    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        array $dateFields,
        ?string $deletedAtColumn,
        bool $force,
        DateTimeInterface $deletedAt,
    ): UpdateBulkBuilder|DeleteBulkBuilder|null
    {
        if ($deletedAtColumn === null || $force) {
            return $this->getDeleteBuilder($eloquent, $data);
        }

        return $this->getUpdateBuilder($eloquent, $data, $dateFields, $deletedAtColumn, $deletedAt);
    }

    /**
     * Build the DELETE statement for the rows which are not skipped.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     *
     * @return \Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder|null Null when there is nothing to delete.
     */
    private function getDeleteBuilder(
        Model $eloquent,
        BulkAccumulationEntity $data
    ): ?DeleteBulkBuilder
    {
        // Only the found rows are deleted, by their primary keys, so the statement
        // cannot reach the rows which the global scopes of the model hide.
        $target = $this->writeKeyResolver->resolve($eloquent, $data->getNotSkippedDeletingModels());

        if ($target['filters'] === []) {
            return null;
        }

        $result = new DeleteBulkBuilder();
        $result->setTable($eloquent->getTable());
        $this->whereClauseApplier->handle($result, $target['columns'], $target['filters']);

        return $result->setLimit(count($target['filters']));
    }

    /**
     * Build the UPDATE statement which fills in the soft-delete column.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param array $dateFields The date format of each date column.
     * @param string $deletedAtColumn
     * @param \DateTimeInterface $deletedAt
     *
     * @return \Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder|null Null when there is nothing to delete.
     */
    private function getUpdateBuilder(
        Model $eloquent,
        BulkAccumulationEntity $data,
        array $dateFields,
        string $deletedAtColumn,
        DateTimeInterface $deletedAt,
    ): ?UpdateBulkBuilder
    {
        $target = $this->writeKeyResolver->resolve($eloquent, $data->getNotSkippedDeletingModels());

        if ($target['filters'] === []) {
            return null;
        }

        $result = new UpdateBulkBuilder();
        $result->setTable($eloquent->getTable());

        // The storage format of the model, as `SoftDeletes::runSoftDelete()` writes it; like
        // there, the update time is written as well when the model has the column.
        $result->addSimpleSet($deletedAtColumn, (string)$eloquent->fromDateTime($deletedAt));

        $updatedAtColumn = $eloquent->getUpdatedAtColumn();

        if ($eloquent->usesTimestamps() && $updatedAtColumn !== null) {
            $result->addSimpleSet($updatedAtColumn, (string)$eloquent->fromDateTime($deletedAt));
        }

        $this->whereClauseApplier->handle($result, $target['columns'], $target['filters']);

        return $result->setLimit(count($target['filters']));
    }
}
