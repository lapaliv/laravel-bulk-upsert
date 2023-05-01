<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 */
class KeyByFeature
{
    public function __construct(private GetUniqueKeyFeature $getUniqueKeyFeature)
    {
        //
    }

    /**
     * @param array<int, array<string, scalar>>|Collection<Model> $rows
     * @param string[] $attributes
     *
     * @return array<string, Model|scalar[]>
     */
    public function handle(array|Collection $rows, array $attributes): array
    {
        $result = [];

        foreach ($rows as $row) {
            $key = $this->getUniqueKeyFeature->handle($row, $attributes);
            $result[$key] = $row;
        }

        return $result;
    }
}
