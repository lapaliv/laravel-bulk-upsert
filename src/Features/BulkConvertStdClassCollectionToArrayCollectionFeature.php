<?php

namespace Lapaliv\BulkUpsert\Features;

class BulkConvertStdClassCollectionToArrayCollectionFeature
{
    /**
     * @param array<int, \stdClass> $rows
     * @return array<int, array<string, scalar>>
     */
    public function handle(array $rows): array
    {
        $result = [];
        foreach ($rows as $key => $value) {
            $result[$key] = (array)$value;
        }

        return $result;
    }
}