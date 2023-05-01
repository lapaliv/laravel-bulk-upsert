<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 */
class GetUniqueKeyFeature
{
    /**
     * @param array<string, scalar>|Model $row
     * @param string[] $attributes
     *
     * @return string
     */
    public function handle(array|Model $row, array $attributes): string
    {
        $key = '';

        foreach ($attributes as $attribute) {
            if ($row instanceof Model) {
                $key .= $row->getAttribute($attribute);
            } else {
                $key .= $row[$attribute];
            }

            $key .= ':';
        }

        return hash('crc32c', $key);
    }
}
