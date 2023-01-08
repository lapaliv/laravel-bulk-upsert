<?php

namespace Lapaliv\BulkUpsert;

use Closure;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\BulkGetDateFieldsFeature;
use Lapaliv\BulkUpsert\Features\BulkUpdateFeature;
use Lapaliv\BulkUpsert\Traits\BulkSettings;
use Illuminate\Database\Eloquent\Collection;

class BulkUpdate
{
    use BulkSettings;

    private ?Closure $updatingCallback = null;
    private ?Closure $updatedCallback = null;

    private array $events = [
        BulkEventEnum::UPDATING,
        BulkEventEnum::UPDATED,
        BulkEventEnum::SAVING,
        BulkEventEnum::SAVED,
    ];

    public function __construct(
        private BulkUpdateFeature $updateFeature,
        private BulkGetDateFieldsFeature $getDateFieldsFeature,
    )
    {
        //
    }

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel> $callback
     * @return $this
     */
    public function onUpdating(callable $callback): static
    {
        $this->updatingCallback = is_callable($callback)
            ? Closure::fromCallable($callback)
            : $callback;

        return $this;
    }

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel> $callback
     * @return $this
     */
    public function onUpdated(callable $callback): static
    {
        $this->updatedCallback = is_callable($callback)
            ? Closure::fromCallable($callback)
            : $callback;

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
        array $updateAttributes = [],
    ): void
    {
        $this->updateByChunks($model, $uniqueAttributes, $updateAttributes, $rows);
    }

    /**
     * @param string|BulkModel $model
     * @param string[]|null $uniqueAttributes
     * @param string[] $updateAttributes
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @return void
     */
    protected function updateByChunks(
        string|BulkModel $model,
        ?array $uniqueAttributes,
        array $updateAttributes,
        iterable $rows,
    ): void
    {
        $model = is_string($model) ? new $model() : $model;
        $uniqueAttributes ??= [$model->getKeyName()];
        $selectColumns = $this->getSelectColumns($uniqueAttributes, $updateAttributes);
        $dateFields = $this->getDateFieldsFeature->handle($model);

        $this->separate(
            $model,
            $rows,
            function (array $chunk) use ($model, $uniqueAttributes, $updateAttributes, $selectColumns, $dateFields): void {
                if ($this->chunkCallback !== null) {
                    $chunk = call_user_func($this->chunkCallback, $chunk) ?? $chunk;
                }

                $this->updateFeature->handle(
                    model: $model,
                    uniqueAttributes: $uniqueAttributes,
                    updateAttributes: $updateAttributes,
                    selectColumns: $selectColumns,
                    dateFields: $dateFields,
                    events: $this->getEvents(),
                    updatingCallback: $this->updatingCallback,
                    updatedCallback: $this->updatedCallback,
                    models: $chunk,
                );
            }
        );
    }

    /**
     * @param string[] $uniqueAttributes
     * @param string[] $updateAttributes
     * @return string[]
     */
    protected function getSelectColumns(
        array $uniqueAttributes,
        array $updateAttributes,
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
