<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\BulkUpdateContract;
use Lapaliv\BulkUpsert\Converters\ArrayToCollectionConverter;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\GetBulkModelFeature;
use Lapaliv\BulkUpsert\Features\GetDateFieldsFeature;
use Lapaliv\BulkUpsert\Features\SeparateIterableRowsFeature;
use Lapaliv\BulkUpsert\Features\UpdateFeature;
use Lapaliv\BulkUpsert\Support\BulkCallback;

class BulkUpdate implements BulkUpdateContract
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
        BulkEventEnum::UPDATING,
        BulkEventEnum::UPDATED,
        BulkEventEnum::SAVING,
        BulkEventEnum::SAVED,
    ];

    private ?BulkCallback $chunkCallback = null;
    private ?BulkCallback $updatingCallback = null;
    private ?BulkCallback $updatedCallback = null;
    private ?BulkCallback $savingCallback = null;
    private ?BulkCallback $savedCallback = null;

    public function __construct(
        private UpdateFeature $updateFeature,
        private GetDateFieldsFeature $getDateFieldsFeature,
        private SeparateIterableRowsFeature $separateIterableRowsFeature,
        private ArrayToCollectionConverter $arrayToCollectionConverter,
        private GetBulkModelFeature $getBulkModelFeature,
    )
    {
        //
    }

    /**
     * @param int $size
     * @param (callable(Collection<BulkModel> $chunk): Collection<BulkModel>)|null $callback
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
            BulkEventEnum::UPDATING,
            BulkEventEnum::UPDATED,
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
     * @param callable(Collection<BulkModel>): Collection<BulkModel> $callback
     * @return $this
     */
    public function onUpdating(?callable $callback): static
    {
        $this->updatingCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel> $callback
     * @return $this
     */
    public function onUpdated(?callable $callback): static
    {
        $this->updatedCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel> $callback
     * @return $this
     */
    public function onSaving(?callable $callback): static
    {
        $this->savingCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel> $callback
     * @return $this
     */
    public function onSaved(?callable $callback): static
    {
        $this->savedCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }

    /**
     * @param string|BulkModel $model
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @param string[]|null $uniqueAttributes
     * @param string[] $updateAttributes
     * @return void
     */
    public function update(
        string|BulkModel $model,
        iterable $rows,
        ?array $uniqueAttributes = null,
        ?array $updateAttributes = null,
    ): void
    {
        $model = $this->getBulkModelFeature->handle($model);
        $uniqueAttributes ??= [$model->getKeyName()];
        $selectColumns = $this->getSelectColumns($uniqueAttributes, $updateAttributes);
        $dateFields = $this->getDateFieldsFeature->handle($model);

        $this->separateIterableRowsFeature->handle(
            $this->chunkSize,
            $rows,
            function (array $chunk) use ($model, $uniqueAttributes, $updateAttributes, $selectColumns, $dateFields): void {
                $collection = $this->arrayToCollectionConverter->handle($model, $chunk);

                $this->updateFeature->handle(
                    eloquent: $model,
                    uniqueAttributes: $uniqueAttributes,
                    updateAttributes: $updateAttributes,
                    selectColumns: $selectColumns,
                    dateFields: $dateFields,
                    events: array_filter(
                        $this->getEvents(),
                        static fn(string $event) => $model::getEventDispatcher()->hasListeners($event)
                    ),
                    updatingCallback: $this->updatingCallback,
                    updatedCallback: $this->updatedCallback,
                    savingCallback: $this->savingCallback,
                    savedCallback: $this->savedCallback,
                    collection: $this->chunkCallback?->handle($collection) ?? $collection,
                );
            }
        );
    }

    /**
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @return string[]
     */
    protected function getSelectColumns(
        array $uniqueAttributes,
        ?array $updateAttributes,
    ): array
    {
        if (in_array('*', $this->selectColumns, true)) {
            return ['*'];
        }

        // the case then we have select(<not all>) and we need to update all attributes
        // looks really strange. The additional fields would mark like a change
        if (empty($updateAttributes)) {
            return ['*'];
        }

        return array_unique(
            array_merge(
                $this->selectColumns,
                $uniqueAttributes,
                $updateAttributes,
            )
        );
    }
}
