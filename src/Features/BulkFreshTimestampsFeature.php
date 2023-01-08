<?php

namespace Lapaliv\BulkUpsert\Features;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

class BulkFreshTimestampsFeature
{
    public function handle(BulkModel $model): void
    {
        if ($model->usesTimestamps()) {
            $now = Carbon::now();

            if ($model->exists === false) {
                $model->setAttribute($model->getCreatedAtColumn(), $now);
            }

            $model->setAttribute($model->getUpdatedAtColumn(), $now);
        }
    }
}
