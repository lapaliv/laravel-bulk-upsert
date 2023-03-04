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
    ) {
        // Nothing
    }

    public function handle(
        BulkModel $eloquent,
        Collection $collection,
        array $uniqueAttributes,
        array $selectColumns,
        ?string $deletedAtColumn,
    ): DividedCollectionByExistingEntity {
        $existing = $collection->filter(
            fn (BulkModel $model) => $model->exists
        );

        $undefined = $collection->filter(
            fn (BulkModel $model) => $model->exists === false
        );

        if ($undefined->isNotEmpty()) {
            $selectedRows = $this->selectExistingRowsFeature->handle(
                $eloquent,
                $undefined,
                $selectColumns,
                $uniqueAttributes,
                $deletedAtColumn,
            );

            $existing->push(...$selectedRows);
        }

        return $this->getDividedCollection($eloquent, $collection, $existing, $uniqueAttributes);
    }

    private function getDividedCollection(
        BulkModel $eloquent,
        Collection $collection,
        Collection $existing,
        array $uniqueAttributes,
    ): DividedCollectionByExistingEntity {
        /** @var array<string, BulkModel> $keyedCollection */
        $keyedCollection = $this->keyByFeature->handle($collection, $uniqueAttributes);
        /** @var array<string, BulkModel> $keyedExisting */
        $keyedExisting = $this->keyByFeature->handle($existing, $uniqueAttributes);

        $nonexistent = $eloquent->newCollection();

        /**
         * @var string $key
         * @var BulkModel $model
         */
        foreach ($keyedCollection as $key => $model) {
            if (array_key_exists($key, $keyedExisting)) {
                foreach ($model->getAttributes() as $attribute => $value) {
                    $keyedExisting[$key]->setAttribute(
                        $attribute,
                        $model->getAttribute($attribute)
                    );
                }
            } else {
                $nonexistent->push($model);
            }
        }

        return new DividedCollectionByExistingEntity(
            $eloquent->newCollection(array_values($keyedExisting)),
            $nonexistent
        );
    }
}
