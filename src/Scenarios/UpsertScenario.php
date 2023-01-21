<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Entities\UpsertConfig;
use Lapaliv\BulkUpsert\Features\DivideCollectionByExistingFeature;
use Lapaliv\BulkUpsert\Features\GetBulkModelFeature;

class UpsertScenario
{
    private Collection $waitingForInsert;
    private Collection $waitingForUpdate;
    private BulkModel $eloquent;

    public function __construct(
        private DivideCollectionByExistingFeature $divideCollectionByExistingFeature,
        private GetBulkModelFeature $getBulkModelFeature,
        private BulkInsert $bulkInsert,
        private BulkUpdate $bulkUpdate,
    ) {
        // Nothing
    }

    public function push(
        BulkModel $eloquent,
        Collection $collection,
        UpsertConfig $config
    ): static {
        if ($collection->isEmpty()) {
            return $this;
        }

        $dividedRows = $this->divideCollectionByExistingFeature->handle(
            $eloquent,
            $collection,
            $config->uniqueAttributes,
            $config->selectColumns
        );

        $this->waitingForInsert->push(...$dividedRows->nonexistent);
        $this->waitingForUpdate->push(...$dividedRows->existing);

        return $this;
    }

    public function insert(UpsertConfig $config, bool $force = false): static
    {
        $collection = $this->sliceCollection('waitingForInsert', $config->chunkSize, $force);

        if ($collection->isNotEmpty()) {
            $this->bulkInsert
                ->chunk($config->chunkSize, $config->chunkCallback?->target)
                ->onCreating($config->creatingCallback?->target)
                ->onCreated($config->createdCallback?->target)
                ->onSaved(
                    function (Collection $collection) use ($config): void {
                        $grouped = $collection->groupBy(
                            fn (BulkModel $model) => $model->wasRecentlyCreated ? 1 : 0
                        );

                        if ($grouped->has(0) && $grouped->get(0)->isNotEmpty()) {
                            $this->waitingForUpdate->push(...$grouped->get(0));
                        }

                        if ($config->savedCallback !== null
                            && $grouped->has(1)
                            && $grouped->get(1)->isNotEmpty()
                        ) {
                            $config->savedCallback->handle($grouped->get(1));
                        }
                    }
                )
                ->setEvents($config->events)
                ->select($config->selectColumns)
                ->insertOrIgnore($this->eloquent, $config->uniqueAttributes, $collection);
        }

        return $this;
    }

    /**
     * @param BulkModel $eloquent
     * @return UpsertScenario
     */
    public function setEloquent(BulkModel $eloquent): static
    {
        $this->eloquent = $eloquent;
        $this->waitingForInsert = new Collection();
        $this->waitingForUpdate = new Collection();

        return $this;
    }

    public function update(UpsertConfig $config, bool $force = false): static
    {
        $collection = $this->sliceCollection('waitingForUpdate', $config->chunkSize, $force);

        if ($collection->isNotEmpty()) {
            $this->bulkUpdate
                ->chunk($config->chunkSize, $config->chunkCallback?->target)
                ->onUpdating($config->updatingCallback?->target)
                ->onUpdated($config->updatedCallback?->target)
                ->onSaving($config->savingCallback?->target)
                ->onSaved($config->savedCallback?->target)
                ->setEvents($config->events)
                ->select($config->selectColumns)
                ->update($this->eloquent, $collection, $config->uniqueAttributes, $config->updateAttributes);
        }

        return $this;
    }

    private function sliceCollection(string $fieldName, int $chunkSize, bool $force): Collection
    {
        $result = new Collection();

        if ($force) {
            $result = $this->{$fieldName};
            $this->{$fieldName} = new Collection();
        } elseif ($chunkSize > 0 && $this->{$fieldName}->count() >= $chunkSize) {
            $length = $chunkSize * (int)floor($this->{$fieldName}->count() / $chunkSize);
            $result = $this->{$fieldName}->slice(0, $length);
            $this->{$fieldName} = $this->{$fieldName}->slice($length);
        }

        return $result;
    }
}
