<?php

namespace Lapaliv\BulkUpsert\Tests\Features;

class GenerateEloquentEventNameFeature
{
    public function handle(string $event, string $model): string
    {
        return sprintf("eloquent.%s: %s", $event, $model);
    }
}
