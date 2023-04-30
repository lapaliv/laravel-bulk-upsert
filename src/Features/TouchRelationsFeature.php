<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;

/**
 * @internal
 */
class TouchRelationsFeature
{
    public function __construct(
        private AddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature
    ) {
        //
    }

    public function handle(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ConnectionInterface $connection,
        BulkDriver $driver,
    ): void {
        if (empty($eloquent->getTouchedRelations())) {
            return;
        }

        $this->touchRelations(
            $eloquent,
            $this->convertModelsToCollection($eloquent, $data),
            $eventDispatcher,
            $connection,
            $driver
        );
    }

    private function convertModelsToCollection(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
    ): Collection {
        $result = $eloquent->newCollection();

        foreach ($data->rows as $row) {
            if ($row->skipSaving) {
                continue;
            }

            $result->push($row->model);
        }

        return $result;
    }

    private function touchRelations(
        Model|BulkModel $eloquent,
        Collection $collection,
        BulkEventDispatcher $eventDispatcher,
        ConnectionInterface $connection,
        BulkDriver $driver,
    ): void {
        foreach ($eloquent->getTouchedRelations() as $relationName) {
            $collection->loadMissing($relationName);
            $this->touch($collection, $eventDispatcher, $connection, $driver, $relationName);
        }
    }

    private function touch(
        Collection $collection,
        BulkEventDispatcher $eventDispatcher,
        ConnectionInterface $connection,
        BulkDriver $driver,
        string $relationName,
    ): void {
        $relations = new Collection();
        $collection->each(
            function (Model $model) use ($relationName, $relations): void {
                $relation = $model->getRelation($relationName);

                if ($relation instanceof Collection) {
                    $relations->push(...$relation);
                } else {
                    $relations->push($relation);
                }
            }
        );

        if ($relations->isEmpty()) {
            return;
        }

        /** @var Model $firstRelation */
        $firstRelation = $relations->first();
        $builder = new UpdateBuilder();
        $builder->table($firstRelation->getTable());

        $filteredRelations = $relations
            ->each(fn (Model $model) => $model->updateTimestamps())
            ->filter(fn (Model $model) => $model->isDirty());

        if ($filteredRelations->isEmpty()) {
            unset($builder, $firstRelation, $relations);

            return;
        }

        $filteredRelations->each(
            function (Model $model) use ($builder) {
                $builder->addSet(
                    $model->getUpdatedAtColumn(),
                    [$model->getKeyName() => $model->getKey()],
                    $model->getAttribute($model->getUpdatedAtColumn())
                );
            }
        );

        $builder->limit($relations->count());
        $this->addWhereClauseToBuilderFeature->handle($builder, ['id'], $relations);

        $updateResult = $driver->update($connection, $builder);

        if ($updateResult > 0) {
            $collection->each(
                function (BulkModel $model) use ($eventDispatcher) {
                    $eventDispatcher->dispatch(BulkEventEnum::SAVED, $model);
                }
            );

            $this->touchRelations($firstRelation, $filteredRelations, $eventDispatcher, $connection, $driver);
        }
    }
}
