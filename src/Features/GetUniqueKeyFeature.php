<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Contracts\BulkModel;

class GetUniqueKeyFeature
{
    /**
     * @param array<string, scalar>|BulkModel $row
     * @param string[] $attributes
     *
     * @return string
     */
    public function handle(array|BulkModel $row, array $attributes): string
    {
        $key = '';

        foreach ($attributes as $attribute) {
            if ($row instanceof BulkModel) {
                $key .= $row->getAttribute($attribute);
            } else {
                $key .= $row[$attribute];
            }

            $key .= ':';
        }

        return hash('crc32c', $key);
    }
}
