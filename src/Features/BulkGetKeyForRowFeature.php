<?php

namespace Lapaliv\BulkUpsert\Features;

class BulkGetKeyForRowFeature
{
    /**
     * @param array<string, scalar> $row
     * @param string[] $attributes
     * @return string
     */
    public function handle(array $row, array $attributes): string
    {
        $key = '';

        foreach ($attributes as $attribute) {
            $key .= $row[$attribute] . ':';
        }

        return md5($key);
    }
}
