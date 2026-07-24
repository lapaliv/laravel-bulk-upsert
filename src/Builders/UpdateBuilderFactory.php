<?php

namespace Lapaliv\BulkUpsert\Builders;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;
use Lapaliv\BulkUpsert\Support\ValueHasher;

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
     */
    public function __construct(
        private AttributesToScalarArrayConverter $attributesToScalarArrayConverter,
        private WhereClauseApplier $whereClauseApplier,
        private ValueHasher $valueHasher,
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

        foreach ($data->getRows() as $row) {
            // `skipSaving` is set by a `saving`/`savingMany` listener that returned false. It has to
            // be honoured here as well, otherwise the cancelled row still ends up in the statement.
            // Keep this in sync with `BulkAccumulationEntity::getNotSkippedUpdatingModels()`, which
            // builds the WHERE clause from the same set of rows.
            if ($row->isSkipSaving() || $row->isSkipUpdating()) {
                continue;
            }

            $this->collectRowAttributes(
                $eloquent,
                $data,
                $row,
                $groupedAttributes,
                $dateFields,
                $deletedAtColumn,
                $updatedAtColumn,
                $updatedAt,
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
            $data->getUniqueBy(),
            $data->getNotSkippedUpdatingModels()
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
    ): void
    {
        $uniqueAttributes = $this->getUniqueAttributeValues($data->getUniqueBy(), $row->getModel(), $dateFields);
        /** @psalm-suppress MixedArgumentTypeCoercion */
        $uniqueAttributesHash = $this->valueHasher->handle(implode(',', $uniqueAttributes));
        $attributes = $this->attributesToScalarArrayConverter->handle(
            $row->getModel(),
            $row->getModel()->getDirty(),
            $dateFields,
        );

        foreach ($data->getUniqueBy() as $uniqueAttribute) {
            unset($attributes[$uniqueAttribute]);
        }

        unset($attributes[$eloquent->getKeyName()]);

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
     * Get the values of the unique attributes of the model as scalars.
     *
     * @param string[] $uniqueBy The names of the unique columns.
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array<string, string> $dateFields
     *
     * @return array<string, mixed>
     */
    private function getUniqueAttributeValues(array $uniqueBy, Model $model, array $dateFields): array
    {
        $result = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($uniqueBy as $unique) {
            $result[$unique] = $model->getAttribute($unique);
        }

        return $this->attributesToScalarArrayConverter->handle($model, $result, $dateFields);
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
