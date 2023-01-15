<?php

namespace Lapaliv\BulkUpsert\Converters;

use Carbon\Carbon;

class ArrayToScalarArrayConverter
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
            $result[$key] = array_key_exists($key, $dateFields)
                ? Carbon::parse($value)->format($dateFields[$key])
                : $value;
        }

        return $result;
    }
}
