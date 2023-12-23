<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

/**
 * @internal
 */
class TouchRelationsFeature
{
    public function __construct(
        private AddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
    ) {
        //
    }

    public function handle(BulkAccumulationEntity $data): void
    {
        if ($data->hasRows()) {
            $this->touch($data->getFirstModel(), $data->getModels());
        }
    }

    private function touch(Model $eloquent, Collection $models, bool $force = false): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $relationNames = $eloquent->getTouchedRelations();

        if (empty($relationNames)) {
            return;
        }

        if ($force) {
            $dirtyModels = $models;
        } else {
            $dirtyModels = $models->filter(
                fn (Model $model) => $model->isDirty() || $model->wasRecentlyCreated
            );
        }

        if ($dirtyModels->isEmpty()) {
            return;
        }

        if (method_exists($dirtyModels, 'loadMissing')) {
            $dirtyModels->loadMissing(...$relationNames);
        }

        foreach ($relationNames as $relationName) {
            $this->touchRelation($models, $relationName);
        }
    }

    private function touchRelation(Collection $collection, string $relationName): void
    {
        $relations = new $collection();

        $collection->map(
            function (Model $model) use ($relationName, $relations): void {
                if (!$model->relationLoaded($relationName)) {
                    return;
                }

                $relation = $model->getRelation($relationName);

                if ($relation instanceof Collection) {
                    $relations->push(...$relation->filter());
                } elseif ($relation instanceof Model) {
                    $relations->push($relation);
                }
            }
        );

        if ($relations->isEmpty()) {
            return;
        }

        $this->freshTimestamps($relations->first(), $relations);
    }

    private function freshTimestamps(Model $eloquent, Collection $relations): void
    {
        if ($relations->isEmpty()) {
            return;
        }

        $now = $eloquent->freshTimestamp();

        /** @var Builder $builder */
        $builder = call_user_func([$eloquent, 'query']);

        $this->addWhereClauseToBuilderFeature->handle($builder, [$eloquent->getKeyName()], $relations);

        $updateResult = $builder->update([
            $eloquent->getUpdatedAtColumn() => $now,
        ]);

        if ($updateResult > 0) {
            $this->touch($relations->first(), $relations, true);
        }
    }
}
