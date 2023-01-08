<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

class BulkConvertArrayToCollectionFeature
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
                $result->put($key, $row);
            } else {
                $item = new $model();
                $item->setRawAttributes($row);

                $result->put($key, $item);
            }
        }

        return $result;
    }
}
