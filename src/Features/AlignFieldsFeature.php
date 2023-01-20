<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Query\Expression;

class AlignFieldsFeature
{
    public function handle(iterable $rows, Expression|null $default = null): array
    {
        $result = [];
        $fields = [];
        $countColumns = 0;
        $needToAlign = false;

        // define fields
        foreach ($rows as $row) {
            $result[] = $row;

            foreach ($row as $key => $value) {
                $fields[$key] = $key;
            }

            if ($countColumns > 0 && count($fields) > $countColumns) {
                $needToAlign = true;
            }

            if ($needToAlign === false) {
                $countColumns = count($fields);
            }
        }

        if ($needToAlign) {
            // align fields
            foreach ($result as $key => $row) {
                foreach ($fields as $column) {
                    $result[$key][$column] = $row[$column] ?? $default;
                }
            }
        }

        return $result;
    }
}
