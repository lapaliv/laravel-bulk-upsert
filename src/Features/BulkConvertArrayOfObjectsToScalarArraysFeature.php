<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use stdClass;

class BulkConvertArrayOfObjectsToScalarArraysFeature
{
    /**
     * @param array<int, stdClass> $rows
     * @return array<int, array<string, scalar>>
     */
    public function handle(array $rows): array
    {
        $result = [];
        foreach ($rows as $key => $value) {
            if ($value instanceof BulkModel) {
                $result[$key] = $value->getAttributes();
            } else {
                $result[$key] = (array)$value;
            }
        }

        return $result;
    }
}
