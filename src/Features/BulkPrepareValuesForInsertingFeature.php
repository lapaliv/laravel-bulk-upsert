<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Query\Expression;

class BulkPrepareValuesForInsertingFeature
{
    /**
     * @param array $fields
     * @param array $rows
     * @return array{values: string[], bindings: string[]}
     */
    public function handle(array $fields, array $rows): array
    {
        $values = [];
        $bindings = [];

        foreach ($rows as $row) {
            $value = [];

            foreach ($fields as $field) {
                if (array_key_exists($field, $row)) {
                    if (is_int($row[$field])) {
                        $value[] = $row[$field];
                    } elseif ($row[$field] === null) {
                        $value[] = 'NULL';
                    } elseif ($row[$field] instanceof Expression) {
                        $value[] = $row[$field]->getValue();
                    } else {
                        $bindings[] = $row[$field];
                        $value[] = '?';
                    }
                } else {
                    $value[] = 'DEFAULT';
                }
            }

            $values[] = sprintf('(%s)', implode(',', $value));
        }

        return compact('values', 'bindings');
    }
}