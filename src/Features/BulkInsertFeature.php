<?php

namespace Lapaliv\BulkUpsert\Features;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkDatabaseDriver;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

class BulkInsertFeature
{
    /**
     * The min primary of the inserted rows.
     *
     * @var int|null
     */
    private ?int $firstInsertedId = null;

    /**
     * The datetime s
     * @var CarbonInterface
     */
    private CarbonInterface $startedAt;

    public function __construct(
        private BulkFireModelEventsFeature $fireModelEventsFeature,
        private BulkGetDriverFeature $getDriverFeature,
        private BulkConvertArrayToCollectionFeature $convertArrayToCollectionFeature,
        private BulkConvertAttributesToScalarArrayFeature $convertAttributesToScalarArrayFeature,
        private BulkFreshTimestampsFeature $freshTimestampsFeature,
    )
    {
        //
    }

    /**
     * @param BulkModel $model
     * @param array $uniqueAttributes
     * @param array $selectColumns
     * @param array $dateFields
     * @param array $events
     * @param bool $ignore
     * @param Closure|null $insertingCallback
     * @param Closure|null $insertedCallback
     * @param BulkModel[] $models
     * @return void
     */
    public function handle(
        BulkModel $model,
        array $uniqueAttributes,
        array $selectColumns,
        array $dateFields,
        array $events,
        bool $ignore,
        ?Closure $insertingCallback,
        ?Closure $insertedCallback,
        array $models,
    ): void
    {
        if (empty($models)) {
            return;
        }

        if ($insertingCallback !== null) {
            $models = $insertingCallback($model->newCollection($models)) ?? $models;
        }

        $this->startedAt = Carbon::now();

        [
            'rows' => $rows,
            'fields' => $fields,
        ] = $this->preparingModels($models, $dateFields, $events);

        if (empty($rows)) {
            return;
        }

        $driver = $this->getDriver(
            $model,
            $uniqueAttributes,
            $selectColumns,
            $rows
        );

        $this->firstInsertedId = $driver->insert($fields, $ignore);

        if ($this->hasToSelect($events, $insertedCallback) === false) {
            return;
        }

        $insertedRows = $driver->selectAffectedRows();
        $collection = $this->convertArrayToCollectionFeature->handle($model, $insertedRows);

        $this->fillWasRecentlyCreated($model, $collection);
        $this->prepareCollection($collection, $events);

        if ($insertedCallback !== null) {
            $insertedCallback($collection);
        }
    }

    /**
     * @param BulkModel[] $models
     * @param string[] $dateFields
     * @return array{
     *     rows: array[],
     *     fields: string[],
     * }
     */
    private function preparingModels(array $models, array $dateFields, array $events): array
    {
        $rows = [];
        $fields = [];

        foreach ($models as $model) {
            $firing = $this->fireModelEventsFeature->handle($model, $events, [
                BulkEventEnum::SAVING,
                BulkEventEnum::CREATING,
            ]);

            if ($firing === false) {
                continue;
            }

            $this->freshTimestampsFeature->handle($model);

            $row = $this->convertAttributesToScalarArrayFeature->handle($dateFields, $model->getAttributes());
            $rows[] = $row;
            $fields[] = array_keys($row);
        }

        return [
            'rows' => $rows,
            'fields' => array_unique(
                array_merge(...$fields)
            ),
        ];
    }

    private function getDriver(
        BulkModel $model,
        array $uniqueAttributes,
        array $selectColumns,
        array $rows,
    ): BulkDatabaseDriver
    {
        return $this->getDriverFeature->handle(
            $model,
            $rows,
            $uniqueAttributes,
            $selectColumns,
        );
    }

    private function hasToSelect(array $events, ?Closure $insertedCallback): bool
    {
        if ($insertedCallback !== null) {
            return true;
        }

        $insertedEvents = array_intersect($events, [
            BulkEventEnum::CREATED,
            BulkEventEnum::SAVED,
        ]);

        return empty($insertedEvents) === false;
    }

    private function fillWasRecentlyCreated(BulkModel $model, Collection $collection): void
    {
        if ($this->firstInsertedId !== null && $model->getIncrementing()) {
            $collection->map(
                fn(BulkModel $model) => $model->wasRecentlyCreated = $model->getKey() >= $this->firstInsertedId
            );
        } elseif ($model->usesTimestamps()) {
            $collection->map(
                function (BulkModel $model) {
                    /** @var CarbonInterface|null $createdAt */
                    $createdAt = $model->getAttribute($model->getCreatedAtColumn());
                    $model->wasRecentlyCreated = $createdAt?->gte($this->startedAt) ?? false;
                }
            );
        }
    }

    private function prepareCollection(Collection $collection, array $events): void
    {
        $collection->map(
            function (BulkModel $model) use ($events): void {
                $this->fireModelEventsFeature->handle($model, $events, [
                    BulkEventEnum::CREATED,
                    BulkEventEnum::SAVED,
                ]);
                $model->syncOriginal();
            }
        );
    }
}
