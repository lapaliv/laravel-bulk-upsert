<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

class FinishSaveFeature
{
    public function __construct(
        private BulkFireModelEventsFeature $fireModelEventsFeature,
        private BulkUpdate $bulkUpdate,
    )
    {
        //
    }

    public function handle(BulkModel $eloquent, Collection $collection, array $events): void
    {
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
            $this->touchRelations($relations);
        }

        $collection->each(
            fn(BulkModel $model) => $model->syncOriginal()
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
     * @return void
     */
    private function touchRelations(array $relations): void
    {
        $this->bulkUpdate->events([BulkEventEnum::SAVED]);

        foreach ($relations as $collection) {
            if ($collection->isNotEmpty()) {
                /** @var BulkModel $model */
                $model = $collection->first();
                $this->bulkUpdate->update(
                    $model,
                    $collection,
                    [$model->getKeyName()],
                    [$model->getUpdatedAtColumn()]
                );
            }
        }
    }
}
