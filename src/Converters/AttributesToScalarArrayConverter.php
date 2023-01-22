<?php

namespace Lapaliv\BulkUpsert\Converters;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Exceptions\BulkAttributeTypeIsNotScalar;

class AttributesToScalarArrayConverter
{
    /**
     * @param string[] $dateFields
     * @param scalar[] $attributes
     * @return mixed[]
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
                throw new BulkAttributeTypeIsNotScalar($key);
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
