<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Builders\UpdateBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;

/**
 * @internal
 */
class GetUpdateBuilderFeature
{
    public function __construct(
        private AttributesToScalarArrayConverter $attributesToScalarArrayConverter,
        private AddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
    ) {
        //
    }

    public function handle(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        array $dateFields,
        ?string $deletedAtColumn,
    ): ?UpdateBuilder {
        $result = new UpdateBuilder();
        $result->table($eloquent->getTable());
        $limit = 0;
        $groupedAttributes = [];

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
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkAccumulationItemEntity $row,
        array &$groups,
        array $dateFields,
        ?string $deletedAtColumn,
    ): void {
        $uniqueAttributes = $this->getUniqueAttributeValues($data->uniqueBy, $row->model, $dateFields);
        $uniqueAttributesHash = hash('crc32c', json_encode($uniqueAttributes, JSON_THROW_ON_ERROR));
        $attributes = $this->attributesToScalarArrayConverter->handle(
            $row->model,
            $row->model->getDirty(),
            $dateFields,
        );

        foreach ($attributes as $key => $value) {
            if (in_array($key, $data->uniqueBy, true) || $key === $eloquent->getKeyName()) {
                continue;
            }

            if ($key === $deletedAtColumn) {
                if ($row->isDeleting && $row->skipDeleting) {
                    continue;
                }

                if ($row->isRestoring && $row->skipRestoring) {
                    continue;
                }
            } else {
                if ($row->skipUpdating) {
                    continue;
                }

                if (!empty($data->updateOnly) && !in_array($key, $data->updateOnly, true)) {
                    continue;
                }

                if (!empty($data->updateExcept) && in_array($key, $data->updateExcept, true)) {
                    continue;
                }
            }

            $valueHash = hash('crc32c', $value . ':' . gettype($value));
            $groups[$key] ??= [];
            $groups[$key][$valueHash] ??= ['value' => $value, 'filters' => []];
            $groups[$key][$valueHash]['filters'][$uniqueAttributesHash] ??= $uniqueAttributes;
        }
    }

    private function getUniqueAttributeValues(array $uniqueBy, BulkModel $model, array $dateFields): array
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
