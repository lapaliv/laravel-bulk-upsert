<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Exceptions\BulkModelIsUndefined;

class GetBulkModelFeature
{
    /**
     * @param class-string<BulkModel>|BulkModel $model
     * @return BulkModel
     */
    public function handle(string|BulkModel $model): BulkModel
    {
        if ($model instanceof BulkModel) {
            return $model;
        }

        if (class_exists($model) === false || is_a($model, BulkModel::class, true) === false) {
            throw new BulkModelIsUndefined();
        }

        return new $model();
    }
}
