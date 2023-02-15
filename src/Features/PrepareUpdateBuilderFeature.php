<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use JsonException;
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
    ) {
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
     * @throws JsonException
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
    ): ?UpdateBuilder {
        if ($collection->isEmpty()) {
            return null;
        }

        $result = new UpdateBuilder();
        $result->table($eloquent->getTable());

        $builderSets = [];

        if ($updatingCallback === null && $savingCallback === null) {
            $updatingCollection = $this->processCollection(
                $eloquent,
                $result,
                $collection,
                $events,
                $uniqueAttributes,
                $updateAttributes,
                $dateFields,
                $builderSets
            );
        } else {
            $updatingCollection = $this->processEachModel(
                $result,
                $eloquent,
                $collection,
                $events,
                $uniqueAttributes,
                $updateAttributes,
                $dateFields,
                $builderSets,
                $updatingCallback,
                $savingCallback,
            );
        }

        if ($updatingCollection->isEmpty()) {
            return null;
        }

        $this->addPreparedSetsToTheBuilder($result, $builderSets, $updatingCollection->count());
        $this->addWhereClauseToBuilderFeature->handle($result, $uniqueAttributes, $updatingCollection);

        return $result;
    }

    /**
     * @param UpdateBuilder $builder
     * @param Collection $collection
     * @param string[] $events
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @param string[] $dateFields
     * @param mixed[] $sets
     * @return Collection
     * @throws JsonException
     */
    private function processCollection(
        BulkModel $eloquent,
        UpdateBuilder $builder,
        Collection $collection,
        array $events,
        array $uniqueAttributes,
        ?array $updateAttributes,
        array $dateFields,
        array &$sets,
    ): Collection {
        $updatingCollection = $eloquent->newCollection();

        /** @var BulkModel $model */
        foreach ($collection as $model) {
            if ($this->fireModelEvents($model, $events) === false) {
                continue;
            }

            if ($model->isDirty() === false) {
                continue;
            }

            $this->freshTimestampsFeature->handle($model);

            $oldLimit = $builder->getLimit() ?? 0;
            $builder->limit($oldLimit + 1);

            $this->prepareBuildersSets($model, $uniqueAttributes, $updateAttributes, $dateFields, $sets);
            $updatingCollection->push($model);
        }

        return $updatingCollection;
    }

    /**
     * @param UpdateBuilder $builder
     * @param BulkModel $eloquent
     * @param Collection $collection
     * @param array $events
     * @param array $uniqueAttributes
     * @param array|null $updateAttributes
     * @param array $dateFields
     * @param array $sets
     * @param BulkCallback|null $updatingCallback
     * @param BulkCallback|null $savingCallback
     * @return bool
     * @throws JsonException
     */
    private function processEachModel(
        UpdateBuilder $builder,
        BulkModel $eloquent,
        Collection $collection,
        array $events,
        array $uniqueAttributes,
        ?array $updateAttributes,
        array $dateFields,
        array &$sets,
        ?BulkCallback $updatingCallback,
        ?BulkCallback $savingCallback,
    ): Collection {
        $collection = $this->prepareModels($collection, $events);
        $collection = $savingCallback?->handle($collection) ?? $collection;

        if ($collection->isEmpty()) {
            return $eloquent->newCollection();
        }

        $collection = $eloquent->newCollection(
            $collection
                ->filter(
                    fn (BulkModel $model) => $model->isDirty()
                )
                ->all()
        );

        $collection = $updatingCallback?->handle($collection) ?? $collection;

        if ($collection->isEmpty()) {
            return $eloquent->newCollection();
        }

        $builder->limit($collection->count());

        $result = $eloquent->newCollection();
        $collection->each(
            function (BulkModel $model) use ($uniqueAttributes, $updateAttributes, $dateFields, &$sets, $result): void {
                $this->prepareBuildersSets(
                    $model,
                    $uniqueAttributes,
                    $updateAttributes,
                    $dateFields,
                    $sets
                );

                $result->push($model);
            }
        );

        return $result;
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
                fn (BulkModel $model) => $this->fireModelEvents($model, $events)
            )
            ->filter(
                fn (BulkModel $model) => $model->isDirty()
            )
            ->each(
                fn (BulkModel $model) => $this->freshTimestampsFeature->handle($model)
            );
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
            $result = array_filter(
                $result,
                static fn (string $key) => in_array($key, $updateAttributes, true),
                ARRAY_FILTER_USE_KEY
            );
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
            $result[$uniqueAttribute] = $model instanceof BulkModel
                ? $model->getAttribute($uniqueAttribute)
                : $model[$uniqueAttribute];
        }

        return $result;
    }

    /**
     * @param BulkModel $model
     * @param array $uniqueAttributes
     * @param array|null $updateAttributes
     * @param array $dateFields
     * @param array $sets
     * @return void
     * @throws JsonException
     */
    private function prepareBuildersSets(
        BulkModel $model,
        array $uniqueAttributes,
        ?array $updateAttributes,
        array $dateFields,
        array &$sets,
    ): void {
        $attributes = $this->getDirtyAttributes($model, $updateAttributes);

        if (empty($attributes)) {
            return;
        }

        $row = $this->arrayToScalarArrayConverter->handle($dateFields, $attributes);

        foreach ($row as $key => $value) {
            if (in_array($key, $uniqueAttributes, true) === false) {
                $valueHash = hash('crc32c', $value . ':' . gettype($value));

                $sets[$key] ??= [];
                $sets[$key][$valueHash] ??= ['value' => $value, 'filters' => []];
                $sets[$key][$valueHash]['filters'][] = $this->getUniqueAttributeValues($model, $uniqueAttributes);
            }
        }
    }

    /**
     * @param array $sets
     * @param int $numberOfRows
     * @return void
     */
    private function addPreparedSetsToTheBuilder(UpdateBuilder $builder, array $sets, int $numberOfRows): void
    {
        foreach ($sets as $field => $values) {
            foreach ($values as $item) {
                ['value' => $value, 'filters' => $filters] = $item;

                if (count($filters) === $numberOfRows) {
                    $builder->addSetWithoutFilters($field, $value);
                } else {
                    foreach ($filters as $filterValues) {
                        $builder->addSet($field, $filterValues, $value);
                    }
                }
            }
        }
    }
}
