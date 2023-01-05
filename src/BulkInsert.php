<?php

namespace Lapaliv\BulkUpsert;

use Closure;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\BulkGetDateFieldsFeature;
use Lapaliv\BulkUpsert\Features\BulkInsertFeature;

class BulkInsert
{
    private BulkModel $model;
    private int $chunkSize = 100;
    private array $selectColumns = ['*'];
    private array $dateFields;

    private ?Closure $chunkCallback;
    private ?Closure $insertingCallback;
    private ?Closure $insertedCallback;

    private array $events = [
        BulkEventEnum::CREATING,
        BulkEventEnum::CREATED,
        BulkEventEnum::SAVING,
        BulkEventEnum::SAVED,
    ];

    public function __construct(
        string|BulkModel $model,
    )
    {
        $this->model = is_string($model) ? new $model() : $model;
    }

    public function chunk(int $size = 100, ?callable $callback = null): static
    {
        $this->chunkSize = $size;

        if ($callback !== null) {
            $this->chunkCallback = is_callable($callback)
                ? Closure::fromCallable($callback)
                : $callback;
        }

        return $this;
    }

    public function setEvents(array $events): static
    {
        $this->events = array_intersect($events, [
            BulkEventEnum::CREATING,
            BulkEventEnum::CREATED,
            BulkEventEnum::SAVING,
            BulkEventEnum::SAVED,
        ]);

        return $this;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function select(array $columns = ['*']): static
    {
        if (in_array('*', $columns, true)) {
            $columns = ['*'];
        } elseif ($this->model->getIncrementing()
            && in_array($this->model->getKeyName(), $columns, true) === false
        ) {
            $columns[] = $this->model->getKeyName();
        } elseif ($this->model->usesTimestamps()
            && in_array($this->model->getCreatedAtColumn(), $columns, true) === false
        ) {
            $columns[] = $this->model->getCreatedAtColumn();
        }

        $this->selectColumns = $columns;

        return $this;
    }

    public function onInserting(callable $callback): static
    {
        $this->insertingCallback = $callback;

        return $this;
    }

    public function onInserted(callable $callback): static
    {
        $this->insertedCallback = is_callable($callback)
            ? Closure::fromCallable($callback)
            : $callback;

        return $this;
    }

    public function insert(
        array    $uniqueColumns,
        iterable $rows
    ): void
    {
        $feature = $this->getFeature($uniqueColumns, false);
        $this->insertByChunks($feature, $rows);
    }

    public function insertOrIgnore(
        array    $uniqueColumns,
        iterable $rows,
    ): void
    {
        $feature = $this->getFeature($uniqueColumns, true);
        $this->insertByChunks($feature, $rows);
    }

    protected function separate(iterable $rows, Closure $callback): void
    {
        if (is_array($rows) && count($rows) <= $this->chunkSize) {
            $chunk = $rows;
        } else {
            $chunk = [];

            foreach ($rows as $key => $row) {
                if ($row instanceof BulkModel) {
                    $chunk[$key] = $row;
                } else {
                    $chunk[$key] = new $this->model();
                    $chunk[$key]->fill((array)$row);
                }

                if (count($chunk) % $this->chunkSize === 0) {
                    $callback($chunk);
                    $chunk = [];
                }
            }
        }

        if (empty($chunk) === false) {
            $callback($chunk);
        }
    }

    protected function insertByChunks(
        BulkInsertFeature $feature,
        iterable          $rows,
    ): void
    {
        $this->separate(
            $rows,
            fn(array $chunk) => $feature->handle(
                $this->convertChunkToArrayOfModels($chunk)
            )
        );
    }

    protected function getFeature(array $uniqueColumns, bool $ignore): BulkInsertFeature
    {
        return new BulkInsertFeature(
            model: $this->model,
            uniqueColumns: $uniqueColumns,
            selectColumns: $this->selectColumns ?? null,
            dateFields: $this->getDateFields(),
            events: $this->events,
            ignore: $ignore,
            insertingCallback: $this->insertingCallback ?? null,
            insertedCallback: $this->insertedCallback ?? null
        );
    }

    protected function convertChunkToArrayOfModels(array $chunk): array
    {
        $chunk = isset($this->chunkCallback)
            ? (call_user_func($this->chunkCallback, $chunk) ?? $chunk)
            : $chunk;

        $result = [];
        foreach ($chunk as $item) {
            if ($item instanceof BulkModel) {
                $result[] = $item;
            } else {
                /** @var \Lapaliv\BulkUpsert\Contracts\BulkModel $model */
                $model = new $this->model();
                $model->fill((array)$item);
                $result[] = $model;
            }
        }

        return $result;
    }

    protected function getDateFields(): array
    {
        if (isset($this->dateFields) === false) {
            $this->dateFields = (new BulkGetDateFieldsFeature($this->model))->handle();
        }

        return $this->dateFields;
    }
}