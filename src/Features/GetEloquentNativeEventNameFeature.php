<?php

namespace Lapaliv\BulkUpsert\Features;

class GetEloquentNativeEventNameFeature
{
    public function handle(string $model, string $event): string
    {
        return sprintf('eloquent.%s: %s', $event, $model);
    }
}
