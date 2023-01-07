<?php

namespace Lapaliv\BulkUpsert\Database\SqlBuilder\Features;

class BulkConvertValueToSqlFeature
{
    public function handle(mixed $value, array &$bindings): string
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if ($value === null) {
            return 'NULL';
        }

        $bindings[] = $value;

        return '?';
    }
}
