<?php

namespace Lapaliv\BulkUpsert\Features;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\BulkUpsert;
use Lapaliv\BulkUpsert\Contracts\BulkDatabaseDriver;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Exceptions\BulkDatabaseDriverIsNotSupported;

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
     * @var \Carbon\CarbonInterface
     */
    private CarbonInterface $startedAt;

    private bool $hasCreatingEvent = false;
    private bool $hasSavingEvent = false;
    private bool $hasCreatedEvent = false;
    private bool $hasSavedEvent = false;

    public function __construct(
        private BulkModel $model,
        private array     $uniqueColumns,
        private array     $selectColumns,
        private array     $dateFields,
        private array     $events,
        private bool      $ignore,
        private ?Closure  $insertingCallback,
        private ?Closure  $insertedCallback,
    )
    {
        foreach ($this->events as $event) {
            switch ($event) {
                case BulkEventEnum::CREATING:
                    $this->hasCreatingEvent = true;
                    break;
                case BulkEventEnum::CREATED:
                    $this->hasCreatedEvent = true;
                    break;
                case BulkEventEnum::SAVING:
                    $this->hasSavingEvent = true;
                    break;
                case BulkEventEnum::SAVED:
                    $this->hasSavedEvent = true;
                    break;
            }
        }
    }

    /**
     * @param BulkModel[] $models
     * @return void
     */
    public function handle(array $models): void
    {
        if (empty($models)) {
            return;
        }

        if ($this->insertingCallback !== null) {
            $callbackResult = call_user_func(
                $this->insertingCallback,
                $this->model->newCollection($models)
            );

            $models = $callbackResult ?? $models;
        }

        [
            'rows' => $rows,
            'fields' => $fields,
        ] = $this->preparingModels($models);

        if (empty($rows)) {
            return;
        }

        $driver = $this->getDriver($rows);

        $this->startedAt = Carbon::now();
        $this->firstInsertedId = $driver->insert($fields, $this->ignore);

        if ($this->hasCreatedEvent === false
            && $this->hasSavedEvent === false
            && $this->insertedCallback === null
        ) {
            return;
        }

        $insertedRows = $driver->selectAffectedRows($this->selectColumns);
        $collection = $this->convertArrayToCollection($insertedRows);

        $this->fillWasRecentlyCreated($collection);
        $this->prepareCollection($collection);

        if ($this->insertedCallback !== null) {
            call_user_func($this->insertedCallback, $collection);
        }
    }

    private function preparingModels(array $models): array
    {
        $rows = [];
        $fields = [];

        foreach ($models as $model) {
            if ($this->fireEventsBeforeInsert($model) === false) {
                continue;
            }

            $this->freshTimestamps($model);

            $row = $this->convertModelToArray($model);
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

    private function convertModelToArray(BulkModel $model): array
    {
        $result = [];

        foreach ($model->getAttributes() as $key => $value) {
            $result[$key] = array_key_exists($key, $this->dateFields)
                ? Carbon::parse($value)->format($this->dateFields[$key])
                : $value;
        }

        return $result;
    }

    private function getDriver(array $rows): BulkDatabaseDriver
    {
        $driverName = $this->model->getConnection()->getDriverName();
        $driver = BulkUpsert::getDatabaseDriver($driverName);

        if ($driver === null) {
            throw new BulkDatabaseDriverIsNotSupported($driverName);
        }

        return $driver->setBuilder($this->model->newQuery())
            ->setConnectionName($this->model->getConnection()->getName())
            ->setRows($rows)
            ->setUniqueAttributes($this->uniqueColumns)
            ->setHasIncrementing($this->model->getIncrementing())
            ->setPrimaryKeyName($this->model->getKeyName())
            ->setSelectColumns($this->selectColumns);
    }

    private function fireEventsBeforeInsert(BulkModel $model): bool
    {
        if ($this->hasSavingEvent && $model->fireModelEvent(BulkEventEnum::SAVING) === false) {
            return false;
        }

        if ($this->hasCreatingEvent && $model->fireModelEvent(BulkEventEnum::CREATING) === false) {
            return false;
        }

        return true;
    }

    private function freshTimestamps(BulkModel $model): void
    {
        if ($model->usesTimestamps()) {
            $model->setAttribute($model->getCreatedAtColumn(), Carbon::now());
            $model->setAttribute($model->getUpdatedAtColumn(), Carbon::now());
        }
    }

    private function convertArrayToCollection(array $rows): Collection
    {
        $result = $this->model->newCollection();

        foreach ($rows as $key => $row) {
            /** @var BulkModel $model */
            $model = new $this->model();
            $model->setRawAttributes($row);

            $result->put($key, $model);
        }

        return $result;
    }

    private function fillWasRecentlyCreated(Collection $collection): void
    {
        if ($this->firstInsertedId !== null && $this->model->getIncrementing()) {
            $collection->map(
                fn(BulkModel $model) => $model->wasRecentlyCreated = $model->getKey() >= $this->firstInsertedId
            );
        } elseif ($this->model->usesTimestamps()) {
            $collection->map(
                function (BulkModel $model) {
                    /** @var CarbonInterface|null $createdAt */
                    $createdAt = $model->getAttribute($model->getCreatedAtColumn());
                    $model->wasRecentlyCreated = $createdAt?->gte($this->startedAt) ?? false;
                }
            );
        }
    }

    private function prepareCollection(Collection $collection): void
    {
        $collection->map(
            function (BulkModel $model): void {
                $this->fireModelEventsAfterInsert($model);
                $this->syncOrigins($model);
            }
        );
    }

    private function fireModelEventsAfterInsert(BulkModel $model): void
    {
        if ($this->hasCreatedEvent && $model->wasRecentlyCreated) {
            $model->fireModelEvent(BulkEventEnum::CREATED, false);
        }

        if ($this->hasSavedEvent) {
            $model->fireModelEvent(BulkEventEnum::SAVED, false);
        }
    }

    private function syncOrigins(BulkModel $model): void
    {
        $model->syncOriginal();
    }
}
