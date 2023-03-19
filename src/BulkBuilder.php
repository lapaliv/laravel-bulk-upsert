<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Lapaliv\BulkUpsert\Features\GetEloquentNativeEventNamesFeature;

class BulkBuilder extends Builder
{
    /**
     * @param iterable $rows
     * @param array $uniqueAttributes
     * @param int $chunk
     * @return void
     */
    public function createMany(array $uniqueAttributes, iterable $rows, int $chunk = 100): void
    {
        /** @var Bulk $bulk */
        $bulk = App::make(Bulk::class);
        $bulk->registerObserver($this)
            ->model($this->getModel())
            ->chunk($chunk)
            ->identifyBy($uniqueAttributes)
            ->insert($rows);
    }

    /**
     * @return Collection<int, Model>
     */
    public function createManyAndReturn(array $uniqueAttributes, iterable $rows, int $chunk = 100)
    {
        /** @var Bulk $bulk */
        $bulk = App::make(Bulk::class);

        return $bulk->registerObserver($this)
            ->model($this->getModel())
            ->chunk($chunk)
            ->identifyBy($uniqueAttributes)
            ->insertAndReturn($rows);
    }

    public function updateMany(array $values, array $uniqueAttributes = [], int $chunk = 100): void
    {
        $model = $this->getModel();

        /** @var GetEloquentNativeEventNamesFeature $getEloquentNativeEventNameFeature */
        $eloquentNativeEventNamesFeature = App::make(GetEloquentNativeEventNamesFeature::class);
        /** @var BulkUpdate $bulkUpdate */
        $bulkUpdate = App::make(BulkUpdate::class);

        $events = $eloquentNativeEventNamesFeature->handle($model, $bulkUpdate->getEvents());

        if (empty($events)) {
            $this->update($values);
            return;
        }

        $updateAttributes = array_keys($values);

        if ($model->usesTimestamps()) {
            $updateAttributes[] = $model->getCreatedAtColumn();
            $updateAttributes[] = $model->getUpdatedAtColumn();
        }

        /** @var Bulk $bulk */
        $bulk = App::make(Bulk::class);
        $bulk->registerObserver($this)
            ->model($model)
            ->chunk($chunk)
            ->identifyBy($uniqueAttributes)
            ->updateOnly(array_unique($updateAttributes));

        $callback = function (Collection $collection) use ($bulk, $model, $uniqueAttributes, $chunk, $updateAttributes, &$result): void {
            $bulk->updateAndReturn($collection);
        };

        if ($model->getIncrementing()) {
            $this->chunkById($chunk, $callback);
        } else {
            $this->chunk($chunk, $callback);
        }
    }
}
