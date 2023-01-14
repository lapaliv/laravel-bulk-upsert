<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Contracts\BulkModel;

class BulkFreshTimestampsFeature
{
    public function handle(BulkModel $model): void
    {
        if ($model->usesTimestamps()) {
            $model->updateTimestamps();
        }
    }
}
