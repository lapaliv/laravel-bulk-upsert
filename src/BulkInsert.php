<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkInsertContract;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Converters\ArrayToCollectionConverter;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\GetDateFieldsFeature;
use Lapaliv\BulkUpsert\Features\InsertFeature;
use Lapaliv\BulkUpsert\Features\SeparateIterableRowsFeature;
use Lapaliv\BulkUpsert\Features\GetBulkModelFeature;
use Lapaliv\BulkUpsert\Support\BulkCallback;

class BulkInsert implements BulkInsertContract
{
    private int $chunkSize = 100;

    /**
     * @var string[]
     */
    private array $selectColumns = ['*'];

    /**
     * @var string[]
     */
    private array $events = [
        BulkEventEnum::CREATING,
        BulkEventEnum::CREATED,
        BulkEventEnum::SAVING,
        BulkEventEnum::SAVED,
    ];

    private ?BulkCallback $chunkCallback = null;
    private ?BulkCallback $creatingCallback = null;
    private ?BulkCallback $createdCallback = null;
    private ?BulkCallback $savedCallback = null;

    public function __construct(
        private InsertFeature $insertFeature,
        private GetDateFieldsFeature $getDateFieldsFeature,
        private ArrayToCollectionConverter $arrayToCollectionConverter,
        private SeparateIterableRowsFeature $separateIterableRowsFeature,
        private GetBulkModelFeature $getBulkModelFeature,
    ) {
        //
    }

    /**
     * @param int $size
     * @param callable(Collection<scalar, BulkModel> $chunk): Collection<scalar, BulkModel>|null $callback
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
     * @param callable(Collection<scalar, BulkModel>): Collection<scalar, BulkModel>|null $callback
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
     * @param callable(Collection<scalar, BulkModel>): Collection<scalar, BulkModel>|null $callback
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
     * @param callable(Collection<scalar, BulkModel>): Collection<scalar, BulkModel>|null $callback
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
     * @param class-string<BulkModel>|BulkModel $model
     * @param string[] $uniqueAttributes
     * @param iterable|Collection<scalar, BulkModel>|array<scalar, array[]> $rows
     * @param bool $ignore
     * @return void
     */
    public function insert(string|BulkModel $model, array $uniqueAttributes, iterable $rows, bool $ignore = false): void
    {
        $model = $this->getBulkModelFeature->handle($model);
        $selectColumns = $this->getSelectColumns($model);
        $dateFields = $this->getDateFieldsFeature->handle($model);

        $this->separateIterableRowsFeature->handle(
            $this->chunkSize,
            $rows,
            function (array $chunk) use ($model, $uniqueAttributes, $ignore, $selectColumns, $dateFields): void {
                $collection = $this->arrayToCollectionConverter->handle($model, $chunk);

                $this->insertFeature->handle(
                    eloquent: $model,
                    uniqueAttributes: $uniqueAttributes,
                    selectColumns: $selectColumns,
                    dateFields: $dateFields,
                    events: array_filter(
                        $this->getEvents(),
                        static fn (string $event) => $model::getEventDispatcher()->hasListeners($event)
                    ),
                    ignore: $ignore,
                    creatingCallback: $this->creatingCallback,
                    createdCallback: $this->createdCallback,
                    savedCallback: $this->savedCallback,
                    collection: $this->chunkCallback?->handle($collection) ?? $collection
                );
            }
        );
    }

    /**
     * @param string|BulkModel $model
     * @param string[] $uniqueAttributes
     * @param iterable|Collection<scalar, BulkModel>|array<scalar, array[]> $rows
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
}
