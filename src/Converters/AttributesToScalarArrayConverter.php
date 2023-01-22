<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

namespace Lapaliv\BulkUpsert\Converters;

use Carbon\Carbon;
use JsonException;
use Lapaliv\BulkUpsert\Exceptions\BulkAttributeTypeIsNotScalar;

class AttributesToScalarArrayConverter
{
    /**
     * @param string[] $dateFields
     * @param scalar[] $attributes
     * @return mixed[]
     * @throws JsonException
     */
    public function handle(array $dateFields, array $attributes): array
    {
        $result = [];

        foreach ($attributes as $key => $value) {
            if ($value !== null && array_key_exists($key, $dateFields)) {
                $value = Carbon::parse($value)->format($dateFields[$key]);
            }

            if (is_object($value)) {
                if (PHP_VERSION_ID >= 80100 && enum_exists(get_class($value))) {
                    $value = $value->value;
                } elseif (method_exists($value, '__toString')) {
                    $value = $value->__toString();
                } else {
                    throw new BulkAttributeTypeIsNotScalar($key);
                }
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_THROW_ON_ERROR);
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
