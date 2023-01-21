<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

class KeyByFeature
{
    public function __construct(
        private GetKeyForRowFeature $getKeyForRowFeature
    )
    {
        // Nothing
    }

    /**
     * @param array<int, array<string, scalar>>|Collection<BulkModel> $rows
     * @param string[] $attributes
     * @return array<string, array<string, scalar>>
     */
    public function handle(array|Collection $rows, array $attributes): array
    {
        $result = [];

        foreach ($rows as $row) {
            $key = $this->getKeyForRowFeature->handle($row, $attributes);
            $result[$key] = $row;
        }

        return $result;
    }
}
