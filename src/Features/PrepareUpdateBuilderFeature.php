<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Support\BulkCallback;

class PrepareUpdateBuilderFeature
{
    public function __construct(
        private FireModelEventsFeature $fireModelEventsFeature,
        private FreshTimestampsFeature $freshTimestampsFeature,
        private AttributesToScalarArrayConverter $arrayToScalarArrayConverter,
        private AddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
        private UpdateBuilder $builder,
    )
    {
        //
    }

    /**
     * There are several cases here:
     * 1. If the both callbacks are empty then we'll prepare models using one loop
     * 2. Otherwise, we need to do it using several loops
     * 3. Another case connects with the same values in one fields.
     *    When we have just one unique attribute then we'll prepare
     *    the compact query using where in (...)
     *
     * @param BulkModel $eloquent
     * @param Collection<BulkModel> $collection
     * @param string[] $events
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @param string[] $dateFields
     * @param BulkCallback|null $updatingCallback
     * @param BulkCallback|null $savingCallback
     * @return UpdateBuilder|null
     */
    public function handle(
        BulkModel $eloquent,
        Collection $collection,
        array $events,
        array $uniqueAttributes,
        ?array $updateAttributes,
        array $dateFields,
        ?BulkCallback $updatingCallback,
        ?BulkCallback $savingCallback,
    ): ?UpdateBuilder
    {
        if ($collection->isEmpty()) {
            return null;
        }

        $this->builder
            ->reset()
            ->table($eloquent->getTable());

        if ($updatingCallback === null && $savingCallback === null) {
            $this->processCollection($collection, $events, $uniqueAttributes, $updateAttributes, $dateFields);
        } else {
            $collection = $this->prepareModels($collection, $events);
            $collection = $savingCallback?->handle($collection) ?? $collection;

            if ($collection->isEmpty()) {
                return null;
            }

            $collection = $eloquent->newCollection(
                $collection
                    ->filter(fn(BulkModel $model) => $model->isDirty())
                    ->toArray()
            );

            $collection = $updatingCallback?->handle($collection) ?? $collection;

            if ($collection->isEmpty()) {
                return null;
            }

            $this->builder->limit($collection->count());

            if (count($uniqueAttributes) > 1) {
                $collection->each(
                    fn(BulkModel $model) => $this->fillInBuilderFromModel(
                        $model,
                        $uniqueAttributes,
                        $updateAttributes,
                        $dateFields,
                    )
                );
            }
        }

        // if $uniqueAttributes contains only one field then
        // we can make cases more compact
        if (count($uniqueAttributes) === 1) {
            $this->fillInBuilderFromCollectionForSingularUniqueAttribute(
                $collection,
                $uniqueAttributes,
                $updateAttributes,
                $dateFields,
            );
        }

        $this->addWhereClauseToBuilderFeature->handle($this->builder, $uniqueAttributes, $collection);

        return $this->builder;
    }

    /**
     * @param Collection $collection
     * @param string[] $events
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @param string[] $dateFields
     * @return void
     */
    private function processCollection(
        Collection $collection,
        array $events,
        array $uniqueAttributes,
        ?array $updateAttributes,
        array $dateFields,
    ): void
    {
        /** @var BulkModel $model */
        foreach ($collection as $model) {
            if ($this->fireModelEvents($model, $events) === false) {
                continue;
            }

            $this->freshTimestampsFeature->handle($model);

            $this->builder->limit($this->builder->getLimit() + 1);

            if (count($uniqueAttributes) > 1) {
                $this->fillInBuilderFromModel($model, $uniqueAttributes, $updateAttributes, $dateFields);
            }
        }
    }

    /**
     * @param BulkModel $model
     * @param string[] $events
     * @return bool
     */
    private function fireModelEvents(BulkModel $model, array $events): bool
    {
        return $this->fireModelEventsFeature->handle($model, $events, [
            BulkEventEnum::SAVING,
            BulkEventEnum::UPDATING,
        ]);
    }

    /**
     * @param Collection<BulkModel> $collection
     * @param string[] $events
     * @return Collection<BulkModel>
     */
    private function prepareModels(Collection $collection, array $events): Collection
    {
        return $collection
            ->filter(
                fn(BulkModel $model) => $this->fireModelEvents($model, $events)
            )
            ->each(
                fn(BulkModel $model) => $this->freshTimestampsFeature->handle($model)
            );
    }

    /**
     * @param Collection<BulkModel> $collection
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @param string[] $dateFields
     * @return void
     */
    private function fillInBuilderFromCollectionForSingularUniqueAttribute(
        Collection $collection,
        array $uniqueAttributes,
        ?array $updateAttributes,
        array $dateFields,
    ): void
    {
        $groupedValues = [];

        foreach ($collection as $model) {
            $attributes = $this->getDirtyAttributes($model, $updateAttributes);

            if (empty($attributes)) {
                continue;
            }

            $row = $this->arrayToScalarArrayConverter->handle($dateFields, $attributes);

            foreach ($row as $key => $value) {
                if (in_array($key, $uniqueAttributes, true)) {
                    continue;
                }

                $valueHash = md5($value);

                $groupedValues[$key] ??= [];
                $groupedValues[$key][$valueHash] ??= ['value' => $value, 'filters' => []];
                $groupedValues[$key][$valueHash]['filters'][] = $this->getUniqueAttributeValues($model, $uniqueAttributes)[0];
            }
        }

        foreach ($groupedValues as $field => $values) {
            foreach ($values as $item) {
                $this->builder->addSet(
                    $field,
                    [$uniqueAttributes[0] => $item['filters']],
                    $item['value'],
                );
            }
        }
    }

    /**
     * @param BulkModel $model
     * @param string[]|null $updateAttributes
     * @return array
     */
    private function getDirtyAttributes(BulkModel $model, ?array $updateAttributes): array
    {
        $result = $model->getDirty();

        if (empty($updateAttributes) === false) {
            $result = array_intersect_key($result, $updateAttributes);
        }

        return $result;
    }

    /**
     * @param BulkModel|mixed[] $model
     * @param string[] $uniqueAttributes
     * @return array<int, mixed>
     */
    private function getUniqueAttributeValues(BulkModel|array $model, array $uniqueAttributes): array
    {
        $result = [];

        foreach ($uniqueAttributes as $uniqueAttribute) {
            $result[] = $model instanceof BulkModel
                ? $model->getAttribute($uniqueAttribute)
                : $model[$uniqueAttribute];
        }

        return $result;
    }

    /**
     * @param BulkModel $model
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @param string[] $dateFields
     * @return void
     */
    private function fillInBuilderFromModel(
        BulkModel $model,
        array $uniqueAttributes,
        ?array $updateAttributes,
        array $dateFields,
    ): void
    {
        $attributes = $this->getDirtyAttributes($model, $updateAttributes);

        if (empty($attributes)) {
            return;
        }

        $row = $this->arrayToScalarArrayConverter->handle($dateFields, $attributes);

        foreach ($row as $key => $value) {
            if (in_array($key, $uniqueAttributes, true) === false) {
                $this->builder->addSet($key, $this->getUniqueAttributeValues($model, $uniqueAttributes), $value);
            }
        }
    }
}
