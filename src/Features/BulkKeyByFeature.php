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
        $result = [];

        foreach ($rows as $row) {
            $key = '';
            foreach ($attributes as $attribute) {
                $key .= $row[$attribute] . ':';
            }

            $result[md5($key)] = $row;
        }

        return $result;
    }
}