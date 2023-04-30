<?php

namespace Lapaliv\BulkUpsert\Converters;

use DateTime;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Exceptions\BulkAttributeTypeIsNotScalar;
use stdClass;

/**
 * @internal
 */
class AttributesToScalarArrayConverter
{
    public function handle(BulkModel $model, array $attributes, array $dateFields): array
    {
        $result = [];

        foreach ($attributes as $key => $value) {
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

            $result[$key] = $value;
        }

        return $result;
    }
}
