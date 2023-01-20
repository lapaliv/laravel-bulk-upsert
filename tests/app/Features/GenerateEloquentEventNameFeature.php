<?php

namespace Lapaliv\BulkUpsert\Tests\App\Features;

class GenerateEloquentEventNameFeature
{
    public function handle(string $event, string $model): string
    {
        return sprintf("eloquent.%s: %s", $event, $model);
    }
}
