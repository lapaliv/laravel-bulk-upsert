<?php

namespace Lapaliv\BulkUpsert\Features;

class BulkKeyByFeature
{
    /**
     * @param array<int, array<string, scalar>> $rows
     * @param string[] $attributes
     * @return array<string, array<string, scalar>>
     */
    public function handle(array $rows, array $attributes): array
    {
        $generateKeyFeature = new BulkGetKeyForRowFeature();
        $result = [];

        foreach ($rows as $row) {
            $key = $generateKeyFeature->handle($row, $attributes);
            $result[$key] = $row;
        }

        return $result;
    }
}
