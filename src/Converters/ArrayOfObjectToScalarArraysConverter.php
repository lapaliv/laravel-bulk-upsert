<?php

namespace Lapaliv\BulkUpsert\Converters;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use stdClass;

class ArrayOfObjectToScalarArraysConverter
{
    /**
     * @param array<int, stdClass> $rows
     * @return array<int, array<string, scalar>>
     */
    public function handle(iterable $rows): array
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
