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

    /**
     * Recursively update the 'updated_at' column value of the related entities.
     *
     * @param BulkAccumulationEntity $data
     *
     * @return void
     */
    public function handle(BulkAccumulationEntity $data): void
    {
        if ($data->hasRows()) {
            $this->processCollection($data->getFirstModel(), $data->getModels());
        }
    }

    /**
     * Define the touching relations, load them, and perform the touch operation.
     *
     * @param Model $eloquent
     * @param Collection $models
     * @param bool $force
     *
     * @return void
     */
    private function processCollection(Model $eloquent, Collection $models, bool $force = false): void
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
            $this->processRelation($models, $relationName);
        }
    }

    /**
     * Prepare and touch the relations.
     *
     * @param Collection $collection
     * @param string $relationName
     *
     * @return void
     */
    private function processRelation(Collection $collection, string $relationName): void
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

        $this->freshUpdatedAt($relations->first(), $relations);
    }

    /**
     * Update the 'updated_at' column value for the given $relations.
     *
     * @param Model $eloquent
     * @param Collection $relations
     *
     * @return void
     */
    private function freshUpdatedAt(Model $eloquent, Collection $relations): void
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
            $this->processCollection($relations->first(), $relations, true);
        }
    }
}
