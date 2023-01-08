<?php

namespace Lapaliv\BulkUpsert\Features;

use Carbon\Carbon;

class BulkConvertAttributesToScalarArrayFeature
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
