<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Converters\ArrayOfObjectToScalarArraysConverter;

class PrepareCollectionBeforeUpdatingFeature
{
    public function __construct(
        private SeparateCollectionByExistingFeature $separateCollectionByExistingFeature,
        private AddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
        private KeyByFeature $keyByFeature,
        private ArrayOfObjectToScalarArraysConverter $arrayOfObjectToScalarArraysConverter,
        private GetKeyForRowFeature $getKeyForRowFeature,
    )
    {
        //
    }

    /**
     * @param BulkModel $eloquent
     * @param string[] $uniqueAttributes
     * @param string[] $updateAttributes
     * @param string[] $selectColumns
     * @param Collection<BulkModel> $collection
     * @return Collection<BulkModel>
     */
    public function handle(
        BulkModel $eloquent,
        array $uniqueAttributes,
        array $updateAttributes,
        array $selectColumns,
        Collection $collection,
    ): Collection
    {
        $result = $eloquent->newCollection();

        [
            'existing' => $existing,
            'nonExistent' => $nonExistent,
        ] = $this->separateCollectionByExistingFeature->handle($collection);

        $result->push(...$existing);

        if ($nonExistent->isNotEmpty()) {
            $foundRows = $this->select(
                $eloquent,
                $uniqueAttributes,
                $selectColumns,
                $nonExistent,
            );

            $result->push(
                ...$this->fillInAttributes($eloquent, $uniqueAttributes, $updateAttributes, $foundRows, $nonExistent),
            );
        }

        return $result;
    }

    /**
     * @param BulkModel $eloquent
     * @param string[] $uniqueAttributes
     * @param string[] $selectColumns
     * @param Collection $collection
     * @return Collection<BulkModel>
     */
    private function select(
        BulkModel $eloquent,
        array $uniqueAttributes,
        array $selectColumns,
        Collection $collection,
    ): Collection
    {
        $builder = $eloquent
            ->newQuery()
            ->select($selectColumns);

        $this->addWhereClauseToBuilderFeature->handle(
            $builder,
            $uniqueAttributes,
            $collection
        );

        return $builder->get();
    }

    /**
     * @param BulkModel $eloquent
     * @param string[] $uniqueAttributes
     * @param string[] $updateAttributes
     * @param Collection<BulkModel> $foundModels
     * @param BulkModel[] $nonExistent
     * @return Collection<BulkModel>
     */
    private function fillInAttributes(
        BulkModel $eloquent,
        array $uniqueAttributes,
        array $updateAttributes,
        Collection $foundModels,
        Collection $nonExistent,
    ): Collection
    {
        $scalarRows = $this->arrayOfObjectToScalarArraysConverter->handle($nonExistent);
        $keyedRows = $this->keyByFeature->handle($scalarRows, $uniqueAttributes);
        $result = $eloquent->newCollection();

        /** @var BulkModel $item */
        foreach ($foundModels as $item) {
            $key = $this->getKeyForRowFeature->handle($item->getAttributes(), $uniqueAttributes);

            if (array_key_exists($key, $keyedRows) === false) {
                continue;
            }

            if (empty($updateAttributes)) {
                $item->fill($keyedRows[$key]);
            } else {
                foreach ($updateAttributes as $attribute) {
                    if (array_key_exists($attribute, $keyedRows[$key])) {
                        $item->setAttribute($attribute, $keyedRows[$key][$attribute]);
                    }
                }
            }

            $result->push($item);
        }

        return $result;
    }
}
