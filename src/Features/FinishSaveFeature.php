<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\Driver;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

class FinishSaveFeature
{
    public function __construct(
        private FireModelEventsFeature    $fireModelEventsFeature,
        private UpdateBuilder             $builder,
        private GetDirtyAttributesFeature $getDirtyAttributesFeature,
    ) {
        //
    }

    /**
     * @param BulkModel $eloquent
     * @param Collection $collection
     * @param ConnectionInterface $connection
     * @param Driver $driver
     * @param string[] $events
     * @return void
     */
    public function handle(
        BulkModel           $eloquent,
        Collection          $collection,
        ConnectionInterface $connection,
        Driver              $driver,
        array               $events,
    ): void {
        if (empty($eloquent->getTouchedRelations())) {
            $collection->each(
                function (BulkModel $model) use ($events): void {
                    $this->fireModelEventsFeature->handle($model, [BulkEventEnum::SAVED], $events);
                    $model->syncOriginal();
                }
            );

            return;
        }

        $collection->each(
            function (BulkModel $model) use ($events): void {
                $this->fireModelEventsFeature->handle($model, [BulkEventEnum::SAVED], $events);
            }
        );

        $relations = $this->getTouchedRelations($eloquent, $collection);

        if (empty($relations) === false) {
            $this->touchRelations($relations, $connection, $driver);
        }

        $collection->each(
            fn (BulkModel $model) => $model->syncOriginal()
        );
    }

    private function getTouchedRelations(BulkModel $eloquent, Collection $collection): array
    {
        /** @var array<string, Collection> $result */
        $result = [];

        /** @var BulkModel $model */
        foreach ($collection as $model) {
            $result = $model->getRelations();

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
     * @return void
     */
    private function touchRelations(
        array               $relations,
        ConnectionInterface $connection,
        Driver              $driver,
    ): void {
        foreach ($relations as $relation) {
            if ($relation instanceof Model) {
                $relation->touch();
                continue;
            }

            if (!$relation instanceof Collection || $relation->isEmpty()) {
                continue;
            }

            /** @var BulkModel $model */
            $model = $relation->first();
            $this->builder->reset()->table($model->getTable());

            if ($model->usesTimestamps() === false) {
                continue;
            }

            $relation = $relation
                ->each(
                    fn (BulkModel $model) => $model->updateTimestamps()
                )
                ->filter(
                    fn (BulkModel $model) => empty($this->getDirtyAttributesFeature->handle($model)) === false
                )
                ->each(
                    fn (BulkModel $model) => $this->builder->addSet(
                        $model->getUpdatedAtColumn(),
                        [$model->getKeyName() => $model->getKey()],
                        $model->getAttribute($model->getUpdatedAtColumn())
                    )
                );

            $updateResult = $driver->update($connection, $this->builder);

            if ($updateResult > 0) {
                $relation->each(
                    fn (BulkModel $model) => $this->fireModelEventsFeature->handle(
                        $model,
                        [BulkEventEnum::SAVED],
                        [BulkEventEnum::SAVED]
                    )
                );
            }
        }
    }
}
