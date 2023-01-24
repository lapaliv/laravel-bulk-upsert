<?php

namespace Lapaliv\BulkUpsert\Converters;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Exceptions\BulkModelIsUndefined;
use stdClass;

class ArrayToCollectionConverter
{
    /**
     * @template TKey of int|string
     *
     * @param BulkModel $model
     * @param array<TKey, BulkModel|scalar[]> $rows
     * @return Collection<TKey, BulkModel>
     */
    public function handle(BulkModel $model, array $rows): Collection
    {
        $result = $model->newCollection();

        foreach ($rows as $key => $row) {
            if ($row instanceof BulkModel) {
                $result->put($key, clone $row);
                continue;
            }

            if (is_object($row)) {
                if (method_exists($row, 'toArray')) {
                    $row = $row->toArray();
                } elseif ($row instanceof stdClass) {
                    $row = (array)$row;
                }
            }

            if (is_array($row) === false) {
                throw new BulkModelIsUndefined();
            }

            $item = new $model();

            foreach ($row as $field => $value) {
                $item->setAttribute($field, $value);
            }

            $result->put($key, $item);
        }

        return $result;
    }
}
