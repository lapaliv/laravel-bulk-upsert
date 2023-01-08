<?php

namespace Lapaliv\BulkUpsert;

use Closure;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\BulkConvertArrayToCollectionFeature;
use Lapaliv\BulkUpsert\Features\BulkGetDateFieldsFeature;
use Lapaliv\BulkUpsert\Features\BulkInsertFeature;
use Lapaliv\BulkUpsert\Traits\BulkSettings;
use Illuminate\Database\Eloquent\Collection;

class BulkInsert
{
    use BulkSettings;

    private ?Closure $insertingCallback = null;
    private ?Closure $insertedCallback = null;

    private array $events = [
        BulkEventEnum::CREATING,
        BulkEventEnum::CREATED,
        BulkEventEnum::SAVING,
        BulkEventEnum::SAVED,
    ];

    public function __construct(
        private BulkInsertFeature $insertFeature,
        private BulkGetDateFieldsFeature $getDateFieldsFeature,
        private BulkConvertArrayToCollectionFeature $convertArrayToCollectionFeature,
    )
    {
        //
    }

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null $callback
     * @return $this
     */
    public function onInserting(?callable $callback): static
    {
        $this->insertingCallback = is_callable($callback)
            ? Closure::fromCallable($callback)
            : $callback;

        return $this;
    }

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null $callback
     * @return $this
     */
    public function onInserted(?callable $callback): static
    {
        $this->insertedCallback = is_callable($callback)
            ? Closure::fromCallable($callback)
            : $callback;

        return $this;
    }

    /**
     * @param string|BulkModel $model
     * @param string[] $uniqueAttributes
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @return void
     */
    public function insert(
        string|BulkModel $model,
        array $uniqueAttributes,
        iterable $rows,
    ): void
    {
        $this->insertByChunks(
            $model,
            $uniqueAttributes,
            $rows,
            ignore: false,
        );
    }

    /**
     * @param string|BulkModel $model
     * @param string[] $uniqueAttributes
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @param bool $ignore
     * @return void
     */
    protected function insertByChunks(
        string|BulkModel $model,
        array $uniqueAttributes,
        iterable $rows,
        bool $ignore
    ): void
    {
        $model = is_string($model) ? new $model() : $model;
        $selectColumns = $this->getSelectColumns($model);
        $dateFields = $this->getDateFieldsFeature->handle($model);

        $this->separate(
            $model,
            $rows,
            function (array $chunk) use ($model, $uniqueAttributes, $ignore, $selectColumns, $dateFields): void {
                if ($this->chunkCallback !== null) {
                    $chunk = call_user_func($this->chunkCallback, $chunk) ?? $chunk;
                }

                $this->insertFeature->handle(
                    $model,
                    $uniqueAttributes,
                    $selectColumns,
                    $dateFields,
                    $this->events,
                    $ignore,
                    $this->insertingCallback,
                    $this->insertedCallback,
                    $this->convertArrayToCollectionFeature->handle($model, $chunk)->all()
                );
            }
        );
    }

    protected function getSelectColumns(BulkModel $model): array
    {
        if (in_array('*', $this->selectColumns, true)) {
            return ['*'];
        }

        if ($model->getIncrementing()) {
            return in_array($model->getKeyName(), $this->selectColumns, true)
                ? $this->selectColumns
                : array_merge($this->selectColumns, [$model->getKeyName()]);
        }

        if ($model->usesTimestamps()) {
            return in_array($model->getCreatedAtColumn(), $this->selectColumns, true)
                ? $this->selectColumns
                : array_merge($this->selectColumns, [$model->getCreatedAtColumn()]);
        }

        return $this->selectColumns;
    }

    /**
     * @param string|BulkModel $model
     * @param string[] $uniqueAttributes
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @return void
     */
    public function insertOrIgnore(
        string|BulkModel $model,
        array $uniqueAttributes,
        iterable $rows,
    ): void
    {
        $this->insertByChunks(
            $model,
            $uniqueAttributes,
            $rows,
            ignore: true,
        );
    }
}
