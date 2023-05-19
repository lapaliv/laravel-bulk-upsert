<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
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
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        ConnectionInterface $connection,
        BulkDriver $driver,
    ): void {
        $models = $eloquent->newCollection();

        foreach ($data->rows as $row) {
            if ($row->skipUpdating || $row->skipCreating) {
                continue;
            }

            $models->push($row->model);
        }

        $this->touchRelations($eloquent, $models, $eventDispatcher, $connection, $driver);
    }

    private function touchRelations(
        Model $eloquent,
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
        $builder = new UpdateBulkBuilder();
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
                function (Model $model) use ($eventDispatcher) {
                    $eventDispatcher->dispatch(BulkEventEnum::SAVED, $model);
                }
            );

            $this->touchRelations($firstRelation, $filteredRelations, $eventDispatcher, $connection, $driver);
        }
    }
}
