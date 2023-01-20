<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

class SeparateCollectionByExistingFeature
{
    /**
     * @param Collection $collection
     * @return array{
     *     existing: Collection<BulkModel>,
     *     nonExistent: Collection<BulkModel>
     * }
     */
    public function handle(Collection $collection): array
    {
        return [
            'existing' => $collection->filter(
                fn (BulkModel $model) => $model->exists,
            ),
            'nonExistent' => $collection->filter(
                fn (BulkModel $model) => $model->exists === false,
            ),
        ];
    }
}
