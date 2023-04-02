<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\Driver;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;

/**
 * @internal
 */
class FinishSaveFeature
{
    /**
     * @param BulkModel $eloquent
     * @param BulkAccumulationEntity $data
     * @param BulkEventDispatcher $eventDispatcher
     * @param ConnectionInterface $connection
     * @param Driver $driver
     *
     * @return void
     */
    public function handle(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ConnectionInterface $connection,
        Driver $driver,
    ): void {
        if (empty($eloquent->getTouchedRelations()) === false) {
            $relations = $this->getTouchedRelations($eloquent, $data);

            if (empty($relations) === false) {
                $this->touchRelations($relations, $eventDispatcher, $connection, $driver);
            }
        }

        $this->syncOriginal($data, $eventDispatcher);
    }

    private function getTouchedRelations(BulkModel $eloquent, BulkAccumulationEntity $data): array
    {
        /** @var array<string, Collection> $result */
        $result = [];

        foreach ($data->rows as $row) {
            if ($row->skipped || $row->model->exists === false) {
                continue;
            }

            $result = $row->model->getRelations();

            foreach ($eloquent->getTouchedRelations() as $relationName) {
                /** @var BulkModel|null $relation */
                $relation = $result[$relationName] ?? null;

                if ($relation !== null) {
                    $result[$relationName] ??= $relation->newCollection();
                    $result[$relationName]->push($relation);
                }
            }
        }

        return $result;
    }

    /**
     * @param Collection[] $relations
     * @param ConnectionInterface $connection
     * @param Driver $driver
     *
     * @return void
     */
    private function touchRelations(
        array $relations,
        BulkEventDispatcher $eventDispatcher,
        ConnectionInterface $connection,
        Driver $driver,
    ): void {
        foreach ($relations as $collection) {
            if ($collection->isEmpty()) {
                continue;
            }

            /** @var BulkModel $model */
            $model = $collection->first();
            $builder = new UpdateBuilder();
            $builder->table($model->getTable());

            if ($model->usesTimestamps() === false) {
                continue;
            }

            $collection = $collection
                ->each(
                    fn (BulkModel $model) => $model->updateTimestamps()
                )
                ->filter(
                    fn (BulkModel $model) => $model->isDirty()
                )
                ->each(
                    fn (BulkModel $model) => $builder->addSet(
                        $model->getUpdatedAtColumn(),
                        [$model->getKeyName() => $model->getKey()],
                        $model->getAttribute($model->getUpdatedAtColumn())
                    )
                );

            $updateResult = $driver->update($connection, $builder);

            if ($updateResult > 0) {
                $collection->each(
                    function (BulkModel $model) use ($eventDispatcher) {
                        $eventDispatcher->dispatch(BulkEventEnum::SAVED, $model);
                    }
                );
            }
        }
    }

    private function syncOriginal(BulkAccumulationEntity $data, BulkEventDispatcher $eventDispatcher): void
    {
        $data->getModels()->each(
            function (BulkModel $model): void {
                $model->syncOriginal();
            }
        );
    }
}
