<?php

namespace Lapaliv\BulkUpsert\Features;

use DateTime;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Exceptions\BulkAttributeTypeIsNotScalar;
use stdClass;

/**
 * @internal
 */
class GetInsertBuilderFeature
{
    public function handle(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        bool $ignore,
        array $dateFields,
    ): ?InsertBuilder {
        $result = new InsertBuilder();
        $result->into($eloquent->getTable())
            ->onConflictDoNothing($ignore);
        $columns = [];

        foreach ($data->rows as $row) {
            if ($row->skipped) {
                continue;
            }

            $array = $this->convertModelToArray($row->model, $dateFields);
            $result->addValue($array);

            foreach ($array as $key => $value) {
                $columns[$key] = $key;
            }
        }

        if (empty($columns)) {
            return null;
        }

        return $result->columns($columns);
    }

    private function convertModelToArray(BulkModel $model, array $dateFields): array
    {
        $result = $model->attributesToArray();

        foreach ($result as $key => $value) {
            if ($value !== null && array_key_exists($key, $dateFields)) {
                $date = new DateTime($value);
                $result[$key] = $date->format($dateFields[$key]);

                continue;
            }

            if (is_object($value)) {
                if (PHP_VERSION_ID >= 80100 && enum_exists(get_class($value))) {
                    $value = $value->value;
                } elseif (method_exists($value, '__toString')) {
                    $value = $value->__toString();
                } elseif ($value instanceof stdClass) {
                    $value = (array) $value;
                } elseif (method_exists($value, 'toArray')) {
                    $value = $value->toArray();
                } elseif ($value instanceof CastsAttributes) {
                    $value = $value->set($model, $key, $value, $result);
                } else {
                    throw new BulkAttributeTypeIsNotScalar($key);
                }
            }

            if (is_array($value)) {
                $value = json_encode($value, JSON_THROW_ON_ERROR);
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
