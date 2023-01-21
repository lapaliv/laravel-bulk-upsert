<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Entities\DividedCollectionByExistingEntity;

class DivideCollectionByExistingFeature
{
    public function __construct(
        private SelectExistingRowsFeature $selectExistingRowsFeature,
        private KeyByFeature $keyByFeature,
    )
    {
        // Nothing
    }

    public function handle(
        BulkModel $eloquent,
        Collection $collection,
        array $uniqueAttributes,
        array $selectColumns,
    ): DividedCollectionByExistingEntity
    {
        $existing = $collection->filter(
            fn(BulkModel $model) => $model->exists
        );

        $undefined = $collection->filter(
            fn(BulkModel $model) => $model->exists === false
        );

        if ($undefined->isNotEmpty()) {
            $existing->push(
                ...$this->selectExistingRowsFeature->handle($eloquent, $undefined, $selectColumns, $uniqueAttributes)
            );
        }

        if ($existing->count() < $collection->count()) {
            return new DividedCollectionByExistingEntity(
                $existing,
                $this->getNonexistent($eloquent, $collection, $existing, $uniqueAttributes)
            );
        }

        return new DividedCollectionByExistingEntity($collection, $eloquent->newCollection());
    }

    /**
     * @param BulkModel $eloquent
     * @param Collection $collection
     * @param Collection $existing
     * @param string[] $uniqueAttributes
     * @return Collection
     */
    private function getNonexistent(
        BulkModel $eloquent,
        Collection $collection,
        Collection $existing,
        array $uniqueAttributes,
    ): Collection
    {
        $keyedCollection = $this->keyByFeature->handle($collection, $uniqueAttributes);
        $keyedExisting = $this->keyByFeature->handle($existing, $uniqueAttributes);

        $result = $eloquent->newCollection();

        foreach ($keyedCollection as $key => $model) {
            if (array_key_exists($key, $keyedExisting) === false) {
                $result->push($model);
            }
        }

        return $result;
    }
}
