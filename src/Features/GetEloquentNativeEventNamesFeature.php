<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Contracts\BulkModel;

class GetEloquentNativeEventNamesFeature
{
    public function handle(BulkModel $model, array $events): array
    {
        return array_filter(
            $events,
            static fn (string $event) => $model::getEventDispatcher()->hasListeners(
                sprintf('eloquent.%s: %s', $event, get_class($model))
            )
        );
    }
}
