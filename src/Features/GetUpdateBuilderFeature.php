<?php

namespace Lapaliv\BulkUpsert\Features;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;
use Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;

/**
 * @internal
 */
class GetUpdateBuilderFeature
{
    private string $updatedAtColumn;
    private string $updatedAt;

    public function __construct(
        private AttributesToScalarArrayConverter $attributesToScalarArrayConverter,
        private AddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
    ) {
        //
    }

    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        array $dateFields,
        ?string $deletedAtColumn,
    ): ?UpdateBuilder {
        $result = new UpdateBuilder();
        $result->table($eloquent->getTable());
        $limit = 0;
        $groupedAttributes = [];
        $this->updatedAtColumn = $eloquent->getUpdatedAtColumn();

        if ($eloquent->usesTimestamps()) {
            $this->updatedAt = Carbon::now()->format(
                $dateFields[$this->updatedAtColumn] ?? 'Y-m-d H:i:s'
            );
        }

        foreach ($data->rows as $row) {
            if ($row->skipUpdating) {
                continue;
            }

            $this->collectRowAttributes(
                $eloquent,
                $data,
                $row,
                $groupedAttributes,
                $dateFields,
                $deletedAtColumn,
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

        $this->addWhereClauseToBuilderFeature->handle(
            $result,
            $data->uniqueBy,
            $data->getNotSkippedModels('skipUpdating')
        );

        return $result->limit($limit);
    }

    private function collectRowAttributes(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkAccumulationItemEntity $row,
        array &$groups,
        array $dateFields,
        ?string $deletedAtColumn,
    ): void {
        $uniqueAttributes = $this->getUniqueAttributeValues($data->uniqueBy, $row->model, $dateFields);
        $uniqueAttributesHash = hash('crc32c', implode(',', $uniqueAttributes));
        $attributes = $this->attributesToScalarArrayConverter->handle(
            $row->model,
            $row->model->getDirty(),
            $dateFields,
        );

        foreach ($data->uniqueBy as $uniqueAttribute) {
            unset($attributes[$uniqueAttribute]);
        }

        unset($attributes[$eloquent->getKeyName()]);

        if ($row->isDeleting && $row->skipDeleting) {
            unset($attributes[$deletedAtColumn]);
        } elseif ($row->isRestoring && $row->skipRestoring) {
            unset($attributes[$deletedAtColumn]);
        }

        if (!empty($data->updateOnly)) {
            foreach ($attributes as $key => $value) {
                if (!in_array($key, $data->updateOnly, true)) {
                    unset($attributes[$key]);
                }
            }
        } elseif (!empty($data->updateExcept)) {
            foreach ($attributes as $key => $value) {
                if (in_array($key, $data->updateExcept, true)) {
                    unset($attributes[$key]);
                }
            }
        }

        if (!empty($attributes)
            && $eloquent->usesTimestamps()
            && !$row->model->isDirty($this->updatedAtColumn)
        ) {
            $attributes[$this->updatedAtColumn] ??= $this->updatedAt;
            $row->model->{$this->updatedAtColumn} = $this->updatedAt;
        }

        foreach ($attributes as $key => $value) {
            $valueHash = hash('crc32c', $value . ':' . gettype($value));
            $groups[$key] ??= [];
            $groups[$key][$valueHash] ??= ['value' => $value, 'filters' => []];
            $groups[$key][$valueHash]['filters'][$uniqueAttributesHash] ??= $uniqueAttributes;
        }
    }

    private function getUniqueAttributeValues(array $uniqueBy, Model $model, array $dateFields): array
    {
        $result = [];

        foreach ($uniqueBy as $unique) {
            $result[$unique] = $model->getAttribute($unique);
        }

        return $this->attributesToScalarArrayConverter->handle($model, $result, $dateFields);
    }

    private function fillSets(UpdateBuilder $builder, array $groupedAttributes): void
    {
        foreach ($groupedAttributes as $attributeName => $valueGroups) {
            foreach ($valueGroups as ['value' => $value, 'filters' => $filterGroups]) {
                foreach ($filterGroups as $filters) {
                    $builder->addSet($attributeName, $filters, $value);
                }
            }
        }
    }
}
