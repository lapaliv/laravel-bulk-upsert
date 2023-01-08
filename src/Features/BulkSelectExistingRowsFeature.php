<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

class BulkSelectExistingRowsFeature
{
    public function __construct(
        private BulkAddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
        private BulkKeyByFeature $keyByFeature,
        private BulkConvertArrayOfObjectsToScalarArraysFeature $convertArrayOfObjectsToScalarArraysFeature,
        private BulkGetKeyForRowFeature $getKeyForRowFeature,
    )
    {
        //
    }

    /**
     * @param BulkModel $model
     * @param string[] $uniqueAttributes
     * @param string[] $updateAttributes
     * @param string[] $selectColumns
     * @param BulkModel[] $models
     * @return Collection<BulkModel>
     */
    public function handle(
        BulkModel $model,
        array $uniqueAttributes,
        array $updateAttributes,
        array $selectColumns,
        array $models,
    ): Collection
    {
        $result = $model->newCollection();

        [
            'exists' => $exists,
            'undefined' => $undefined,
        ] = $this->divideByExists($models);

        $result->push(...$exists);

        if (empty($undefined) === false) {
            $foundRows = $this->select(
                $model,
                $uniqueAttributes,
                $selectColumns,
                $this->prepareRowsForSelect($uniqueAttributes, $undefined),
            );

            $result->push(
                ...$this->fillInAttributes($model, $uniqueAttributes, $updateAttributes, $foundRows, $undefined),
            );
        }

        return $result;
    }

    /**
     * @param BulkModel[] $models
     * @return array{
     *     exists: BulkModel[],
     *     undefined: BulkModel[],
     * }
     */
    private function divideByExists(array $models): array
    {
        $result = [
            'exists' => [],
            'undefined' => [],
        ];

        array_map(
            static function (BulkModel $model) use (&$result): void {
                if ($model->exists) {
                    $result['exists'][] = $model;
                } else {
                    $result['undefined'][] = $model;
                }
            },
            $models,
        );

        return $result;
    }

    /**
     * @param BulkModel $model
     * @param string[] $uniqueAttributes
     * @param string[] $selectColumns
     * @param BulkModel[] $models
     * @return Collection<BulkModel>
     */
    private function select(
        BulkModel $model,
        array $uniqueAttributes,
        array $selectColumns,
        array $models,
    ): Collection
    {
        $builder = $model
            ->newQuery()
            ->select($selectColumns);

        $this->addWhereClauseToBuilderFeature->handle(
            $builder,
            $uniqueAttributes,
            $models
        );

        return $builder->get();
    }

    /**
     * @param BulkModel[] $models
     * @return BulkModel[]
     */
    private function prepareRowsForSelect(
        array $uniqueAttributes,
        array $models
    ): array
    {
        $result = [];

        foreach ($models as $model) {
            $item = [];
            foreach ($uniqueAttributes as $attribute) {
                $item[$attribute] = $model->getAttribute($attribute);
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param BulkModel $model
     * @param string[] $uniqueAttributes
     * @param string[] $updateAttributes
     * @param Collection<BulkModel> $collection
     * @param BulkModel[] $models
     * @return Collection<BulkModel>
     */
    private function fillInAttributes(
        BulkModel $model,
        array $uniqueAttributes,
        array $updateAttributes,
        Collection $collection,
        array $models,
    ): Collection
    {
        $scalarRows = $this->convertArrayOfObjectsToScalarArraysFeature->handle($models);
        $keyedRows = $this->keyByFeature->handle($scalarRows, $uniqueAttributes);
        $result = $model->newCollection();

        /** @var BulkModel $item */
        foreach ($collection as $item) {
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
