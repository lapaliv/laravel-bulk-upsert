<?php

namespace Lapaliv\BulkUpsert\Scenarios\Shared;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Builders\WhereClauseApplier;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

/**
 * Touches the relations of the models which have been created or updated.
 *
 * @internal
 */
readonly class TouchRelationsStep
{
    /**
     * Create a new step instance.
     *
     * @param \Lapaliv\BulkUpsert\Builders\WhereClauseApplier $whereClauseApplier
     */
    public function __construct(
        private WhereClauseApplier $whereClauseApplier,
    )
    {
        //
    }

    /**
     * Recursively update the 'updated_at' column value of the related entities.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     *
     * @return void
     */
    public function handle(BulkAccumulationEntity $data): void
    {
        if ($data->hasRows()) {
            /** @var \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $models */
            $models = $data->getModels();

            $this->processCollection($data->getFirstModel(), $models);
        }
    }

    /**
     * Define the touching relations, load them, and perform the touch operation.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     * @param bool $force
     *
     * @return void
     */
    private function processCollection(Model $eloquent, Collection $models, bool $force = false): void
    {
        if ($models->isEmpty()) {
            return;
        }

        /** @var string[] $relationNames */
        $relationNames = $eloquent->getTouchedRelations();

        if (empty($relationNames)) {
            return;
        }

        if ($force) {
            $dirtyModels = $models;
        } else {
            $dirtyModels = $models->filter(
                fn(Model $model) => $model->isDirty() || $model->wasRecentlyCreated
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
     * @param \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $collection
     * @param string $relationName
     *
     * @return void
     */
    private function processRelation(Collection $collection, string $relationName): void
    {
        $firstModel = $collection->first();

        if ($firstModel === null) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $relations */
        $relations = new EloquentCollection();

        $collection->map(
            function (Model $model) use ($relationName, $relations): void {
                if (!$model->relationLoaded($relationName)) {
                    return;
                }

                /** @psalm-suppress MixedAssignment */
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

        /** @var \Illuminate\Database\Eloquent\Model $firstRelation */
        $firstRelation = $relations->first();

        $this->freshUpdatedAt($firstRelation, $relations);
    }

    /**
     * Update the 'updated_at' column value for the given $relations.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $relations
     *
     * @return void
     */
    private function freshUpdatedAt(Model $eloquent, Collection $relations): void
    {
        if ($relations->isEmpty()) {
            return;
        }

        $updatedAtColumn = $eloquent->getUpdatedAtColumn();

        if ($updatedAtColumn === null) {
            return;
        }

        $now = $eloquent->freshTimestamp();
        $builder = $eloquent->newQuery();

        $this->whereClauseApplier->handle($builder, [$eloquent->getKeyName()], $relations);

        $updateResult = $builder->update([
            $updatedAtColumn => $now,
        ]);

        if ($updateResult > 0) {
            /** @var \Illuminate\Database\Eloquent\Model $firstRelation */
            $firstRelation = $relations->first();

            $this->processCollection($firstRelation, $relations, true);
        }
    }
}
