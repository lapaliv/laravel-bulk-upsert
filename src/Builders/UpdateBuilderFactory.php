<?php

namespace Lapaliv\BulkUpsert\Builders;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;
use Lapaliv\BulkUpsert\Support\ValueHasher;
use Lapaliv\BulkUpsert\Support\WriteKeyResolver;
use SplObjectStorage;

/**
 * Builds the UPDATE statement for the given models.
 *
 * The rows which share the same new value of a column are grouped together,
 * so that one CASE branch can cover several rows at once.
 *
 * @internal
 */
readonly class UpdateBuilderFactory
{
    /**
     * Create a new factory instance.
     *
     * @param \Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter $attributesToScalarArrayConverter
     * @param \Lapaliv\BulkUpsert\Builders\WhereClauseApplier $whereClauseApplier
     * @param \Lapaliv\BulkUpsert\Support\ValueHasher $valueHasher
     * @param \Lapaliv\BulkUpsert\Support\WriteKeyResolver $writeKeyResolver
     */
    public function __construct(
        private AttributesToScalarArrayConverter $attributesToScalarArrayConverter,
        private WhereClauseApplier $whereClauseApplier,
        private ValueHasher $valueHasher,
        private WriteKeyResolver $writeKeyResolver,
    )
    {
        //
    }

    /**
     * Build the UPDATE statement for the rows which are not skipped.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param array $dateFields The date format of each date column.
     * @param string|null $deletedAtColumn
     *
     * @return \Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder|null Null when there is nothing to update.
     */
    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        array $dateFields,
        ?string $deletedAtColumn,
    ): ?UpdateBulkBuilder
    {
        /** @var array<string, string> $dateFields */
        $result = new UpdateBulkBuilder();
        $result->setTable($eloquent->getTable());
        $limit = 0;
        $groupedAttributes = [];
        $updatedAtColumn = $eloquent->getUpdatedAtColumn();
        $updatedAt = null;

        if ($eloquent->usesTimestamps() && $updatedAtColumn !== null) {
            $updatedAt = Carbon::now()->format(
                $dateFields[$updatedAtColumn] ?? 'Y-m-d H:i:s'
            );
        }

        // The statement is written by the primary keys of the found rows, so it cannot reach
        // the rows which the global scopes of the model hide.
        $target = $this->writeKeyResolver->resolve($eloquent, $data->getNotSkippedUpdatingModels());
        /** @var SplObjectStorage<\Illuminate\Database\Eloquent\Model, array<string, scalar>> $filters */
        $filters = new SplObjectStorage();

        foreach ($target['models'] as $index => $model) {
            $filters[$model] = $target['filters'][$index];
        }

        foreach ($data->getRows() as $row) {
            // `skipSaving` is set by a `saving`/`savingMany` listener that returned false. It has to
            // be honoured here as well, otherwise the cancelled row still ends up in the statement.
            // The rows which cannot be identified by the write key are not written either.
            if ($row->isSkipSaving() || $row->isSkipUpdating() || !$filters->contains($row->getModel())) {
                continue;
            }

            $filter = $filters[$row->getModel()];

            $this->collectRowAttributes(
                $eloquent,
                $data,
                $row,
                $groupedAttributes,
                $dateFields,
                $deletedAtColumn,
                $updatedAtColumn,
                $updatedAt,
                $filter,
            );
            ++$limit;
        }

        if ($limit === 0) {
            return null;
        }

        $this->fillSets($result, $groupedAttributes);

        if (empty($result->getSets())) {
            return null;
        }

        /** @psalm-suppress InvalidArgument */
        $this->whereClauseApplier->handle(
            $result,
            $target['columns'],
            $target['filters']
        );

        return $result->setLimit($limit);
    }

    /**
     * Collect the changed attributes of the row into the groups of the values.
     *
     * The unique attributes and the primary key are never updated. The soft-delete
     * column is dropped when the deletion or the restoring of the row was cancelled.
     * The `updateOnly` and `updateExcept` lists of the data limit the columns further.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity $row
     * @param array<string, array<string, array{value: mixed, filters: array<string, array<string, mixed>>}>> $groups
     * @param array<string, string> $dateFields The date format of each date column.
     * @param string|null $deletedAtColumn
     * @param string|null $updatedAtColumn
     * @param string|null $updatedAt
     * @param array<string, mixed> $filter The scalar values of the columns which identify the row.
     *
     * @return void
     */
    private function collectRowAttributes(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkAccumulationItemEntity $row,
        array &$groups,
        array $dateFields,
        ?string $deletedAtColumn,
        ?string $updatedAtColumn,
        ?string $updatedAt,
        array $filter,
    ): void
    {
        $uniqueAttributes = $filter;
        // The whole filter is serialized, so the parts of a composite key keep their boundaries.
        $uniqueAttributesHash = serialize($uniqueAttributes);
        $attributes = $this->attributesToScalarArrayConverter->handle(
            $row->getModel(),
            $row->getModel()->getDirty(),
            $dateFields,
        );

        foreach ($data->getUniqueBy() as $uniqueAttribute) {
            unset($attributes[$uniqueAttribute]);
        }

        unset($attributes[$eloquent->getKeyName()]);

        // The primary key is never written, so the model has to keep the stored one.
        // Otherwise `syncOriginal()` would remember the changed key, and the next write
        // of the same model would reach another row.
        $model = $row->getModel();
        $keyName = $eloquent->getKeyName();

        if ($model->exists && $keyName !== '' && $model->isDirty($keyName)) {
            $model->setAttribute($keyName, $model->getRawOriginal($keyName));
        }

        if ($deletedAtColumn !== null) {
            if ($row->isDeleting() && $row->isSkipDeleting()) {
                unset($attributes[$deletedAtColumn]);
            } elseif ($row->isRestoring() && $row->isSkipRestoring()) {
                unset($attributes[$deletedAtColumn]);
            }
        }

        if (!empty($data->getUpdateOnly())) {
            foreach (array_keys($attributes) as $key) {
                if (!in_array($key, $data->getUpdateOnly(), true)) {
                    unset($attributes[$key]);
                }
            }
        } elseif (!empty($data->getUpdateExcept())) {
            foreach (array_keys($attributes) as $key) {
                if (in_array($key, $data->getUpdateExcept(), true)) {
                    unset($attributes[$key]);
                }
            }
        }

        if (!empty($attributes)
            && $updatedAtColumn !== null
            && $updatedAt !== null
            && !$row->getModel()->isDirty($updatedAtColumn)
        ) {
            /** @psalm-suppress MixedAssignment */
            $attributes[$updatedAtColumn] ??= $updatedAt;
            $row->getModel()->{$updatedAtColumn} = $updatedAt;
        }

        /** @psalm-suppress MixedAssignment */
        foreach ($attributes as $key => $value) {
            $valueHash = $this->valueHasher->handle($value);
            $groups[$key] ??= [];
            $groups[$key][$valueHash] ??= ['value' => $value, 'filters' => []];
            $groups[$key][$valueHash]['filters'][$uniqueAttributesHash] ??= $uniqueAttributes;
        }
    }

    /**
     * Add the collected values to the SET part of the statement.
     *
     * @param \Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder $builder
     * @param array<string, array<string, array{value: mixed, filters: array<string, array<string, mixed>>}>> $groupedAttributes
     *
     * @return void
     */
    private function fillSets(UpdateBulkBuilder $builder, array $groupedAttributes): void
    {
        foreach ($groupedAttributes as $attributeName => $valueGroups) {
            foreach ($valueGroups as ['value' => $value, 'filters' => $filterGroups]) {
                foreach ($filterGroups as $filters) {
                    /** @psalm-suppress MixedArgumentTypeCoercion, MixedArgument */
                    $builder->addSet($attributeName, $filters, $value);
                }
            }
        }
    }
}
