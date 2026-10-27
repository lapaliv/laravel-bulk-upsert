<?php

namespace Lapaliv\BulkUpsert\Scenarios\Shared;

use Closure;
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
     * Without `$written`, the rows which are dirty or have recently been created touch their
     * relations. With it, exactly the rows it accepts do: the scenario which knows what it has
     * written decides, not the state of the models, which listeners may change.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param (\Closure(\Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity): bool)|null $written
     *
     * @return void
     */
    public function handle(BulkAccumulationEntity $data, ?Closure $written = null): void
    {
        if (!$data->hasRows()) {
            return;
        }

        if ($written === null) {
            /** @var \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $models */
            $models = $data->getModels();

            $this->processCollection($data->getFirstModel(), $models);

            return;
        }

        $selected = [];

        foreach ($data->getRows() as $row) {
            if ($written($row)) {
                $selected[] = $row->getModel();
            }
        }

        $this->processCollection($data->getFirstModel(), $data->getFirstModel()->newCollection($selected), true);
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
            // Only the selected models touch: a clean neighbour must not touch its parents.
            $this->processRelation($dirtyModels, $relationName);
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
