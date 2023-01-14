<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkInsertContract;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Exceptions\BulkModelIsUndefined;
use Lapaliv\BulkUpsert\Features\BulkConvertArrayToCollectionFeature;
use Lapaliv\BulkUpsert\Features\BulkGetDateFieldsFeature;
use Lapaliv\BulkUpsert\Features\BulkInsertFeature;
use Lapaliv\BulkUpsert\Features\BulkSeparateIterableRowsFeature;
use Lapaliv\BulkUpsert\Support\BulkCallback;

class BulkInsert implements BulkInsertContract
{
    private int $chunkSize = 100;

    /**
     * @var string[]
     */
    private array $selectColumns = ['*'];

    private ?BulkCallback $chunkCallback = null;
    private ?BulkCallback $creatingCallback = null;
    private ?BulkCallback $createdCallback = null;
    private ?BulkCallback $savedCallback = null;

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
        private BulkSeparateIterableRowsFeature $separateIterableRowsFeature,
    )
    {
        //
    }

    /**
     * @param int $size
     * @param callable(Collection<BulkModel> $chunk): BulkModel[]|null $callback
     * @return $this
     */
    public function chunk(int $size = 100, ?callable $callback = null): static
    {
        $this->chunkSize = $size;
        $this->chunkCallback = $callback === null
            ? null
            : new BulkCallback($callback);

        return $this;
    }

    /**
     * @return string[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param string[] $events
     * @return $this
     */
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

    public function disableEvents(): static
    {
        $this->events = [];

        return $this;
    }

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null $callback
     * @return $this
     */
    public function onCreating(?callable $callback): static
    {
        $this->creatingCallback = $callback === null
            ? $callback
            : new BulkCallback($callback);

        return $this;
    }

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null $callback
     * @return $this
     */
    public function onCreated(?callable $callback): static
    {
        $this->createdCallback = $callback === null
            ? $callback
            : new BulkCallback($callback);

        return $this;
    }

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null $callback
     * @return $this
     */
    public function onSaved(?callable $callback): static
    {
        $this->createdCallback = $callback === null
            ? $callback
            : new BulkCallback($callback);

        return $this;
    }

    /**
     * @param string[] $columns
     * @return $this
     */
    public function select(array $columns = ['*']): static
    {
        $this->selectColumns = in_array('*', $columns, true)
            ? ['*']
            : $columns;

        return $this;
    }

    /**
     * @param string|BulkModel $model
     * @param string[] $uniqueAttributes
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @param bool $ignore
     * @return void
     */
    public function insert(string|BulkModel $model, array $uniqueAttributes, iterable $rows, bool $ignore = false): void
    {
        $model = $this->getBulkModel($model);
        $selectColumns = $this->getSelectColumns($model);
        $dateFields = $this->getDateFieldsFeature->handle($model);

        $this->separateIterableRowsFeature->handle(
            $this->chunkSize,
            $rows,
            function (array $chunk) use ($model, $uniqueAttributes, $ignore, $selectColumns, $dateFields): void {
                $collection = $this->convertArrayToCollectionFeature->handle($model, $chunk);

                $this->insertFeature->handle(
                    $model,
                    $uniqueAttributes,
                    $selectColumns,
                    $dateFields,
                    array_filter(
                        $this->getEvents(),
                        static fn(string $event) => $model::getEventDispatcher()->hasListeners($event)
                    ),
                    $ignore,
                    $this->creatingCallback,
                    $this->createdCallback,
                    $this->savedCallback,
                    $this->chunkCallback?->handle($collection) ?? $collection
                );
            }
        );
    }

    /**
     * @param string|BulkModel $model
     * @param string[] $uniqueAttributes
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @return void
     */
    public function insertOrIgnore(string|BulkModel $model, array $uniqueAttributes, iterable $rows): void
    {
        $this->insert($model, $uniqueAttributes, $rows, true);
    }

    /**
     * @return string[]
     */
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

    protected function getBulkModel(BulkModel|string $model): BulkModel
    {
        if ($model instanceof BulkModel) {
            return $model;
        }

        if (class_exists($model) === false || is_a($model, BulkModel::class) === false) {
            throw new BulkModelIsUndefined();
        }

        return new $model();
    }
}
