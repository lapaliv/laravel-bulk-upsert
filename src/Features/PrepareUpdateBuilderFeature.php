<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use JsonException;
use Lapaliv\BulkUpsert\Builders\UpdateBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter;
use Lapaliv\BulkUpsert\Entities\BulkScenarioConfig;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

class PrepareUpdateBuilderFeature
{
    public function __construct(
        private FireModelEventsFeature $fireModelEventsFeature,
        private FreshTimestampsFeature $freshTimestampsFeature,
        private AttributesToScalarArrayConverter $arrayToScalarArrayConverter,
        private AddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
        private GetDirtyAttributesFeature $getDirtyAttributesFeature,
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
     * @param Collection $collection
     * @param BulkScenarioConfig $scenarioConfig
     * @return UpdateBuilder|null
     * @throws JsonException
     */
    public function handle(
        BulkModel $eloquent,
        Collection $collection,
        BulkScenarioConfig $scenarioConfig,
    ): ?UpdateBuilder {
        if ($collection->isEmpty()) {
            return null;
        }

        $result = new UpdateBuilder();
        $result->table($eloquent->getTable());

        $builderSets = [];

        if ($this->canProcessCollection($scenarioConfig)) {
            $updatingCollection = $this->processCollection(
                $eloquent,
                $result,
                $collection,
                $scenarioConfig,
                $builderSets
            );
        } else {
            $updatingCollection = $this->processEachModel(
                $result,
                $eloquent,
                $collection,
                $scenarioConfig,
                $builderSets,
            );
        }

        if ($updatingCollection->isEmpty()) {
            return null;
        }

        $this->addPreparedSetsToTheBuilder($result, $builderSets, $updatingCollection->count());
        $this->addWhereClauseToBuilderFeature->handle($result, $scenarioConfig->uniqueAttributes, $updatingCollection);

        return $result;
    }

    /**
     * @param BulkModel $eloquent
     * @param UpdateBuilder $builder
     * @param Collection $collection
     * @param BulkScenarioConfig $scenarioConfig
     * @param mixed[] $sets
     * @return Collection
     * @throws JsonException
     */
    private function processCollection(
        BulkModel $eloquent,
        UpdateBuilder $builder,
        Collection $collection,
        BulkScenarioConfig $scenarioConfig,
        array &$sets,
    ): Collection {
        $updatingCollection = $eloquent->newCollection();

        /** @var BulkModel $model */
        foreach ($collection as $model) {
            if ($this->fireModelEvents($model, $scenarioConfig) === false) {
                continue;
            }

            if (empty($this->getDirtyAttributesFeature->handle($model))) {
                continue;
            }

            $this->freshTimestampsFeature->handle($model);

            $oldLimit = $builder->getLimit() ?? 0;
            $builder->limit($oldLimit + 1);

            $this->prepareBuildersSets($model, $scenarioConfig, $sets);
            $updatingCollection->push($model);
        }

        return $updatingCollection;
    }

    /**
     * @param UpdateBuilder $builder
     * @param BulkModel $eloquent
     * @param Collection $collection
     * @param BulkScenarioConfig $scenarioConfig
     * @param array $sets
     * @return Collection
     * @throws JsonException
     */
    private function processEachModel(
        UpdateBuilder $builder,
        BulkModel $eloquent,
        Collection $collection,
        BulkScenarioConfig $scenarioConfig,
        array &$sets,
    ): Collection {
        $collection = $this->prepareModels($collection, $scenarioConfig);
        $collection = $scenarioConfig->savingCallback?->handle($collection) ?? $collection;

        if ($collection->isEmpty()) {
            return $eloquent->newCollection();
        }

        $collection = $eloquent->newCollection(
            $collection
                ->filter(
                    fn (BulkModel $model) => empty($this->getDirtyAttributesFeature->handle($model)) === false
                )
                ->all()
        );

        $collection = $scenarioConfig->updatingCallback?->handle($collection) ?? $collection;
        $collection = $this->runDeletingOrRestoringCallbacks($eloquent, $scenarioConfig, $collection);

        if ($collection->isEmpty()) {
            return $eloquent->newCollection();
        }

        $builder->limit($collection->count());

        $result = $eloquent->newCollection();
        $collection->each(
            function (BulkModel $model) use ($scenarioConfig, &$sets, $result): void {
                $this->prepareBuildersSets($model, $scenarioConfig, $sets);

                $result->push($model);
            }
        );

        return $result;
    }

    /**
     * @param BulkModel $model
     * @param BulkScenarioConfig $scenarioConfig
     * @return bool
     */
    private function fireModelEvents(BulkModel $model, BulkScenarioConfig $scenarioConfig): bool
    {
        $events = [
            BulkEventEnum::SAVING,
            BulkEventEnum::UPDATING,
        ];

        if ($scenarioConfig->deletedAtColumn !== null) {
            $actualDeletedAt = $model->getAttribute($scenarioConfig->deletedAtColumn);
            $originalDeletedAt = $model->getOriginal($scenarioConfig->deletedAtColumn);

            if ($actualDeletedAt === null && $originalDeletedAt !== null) {
                $events[] = BulkEventEnum::RESTORING;
            } elseif ($actualDeletedAt !== null && $originalDeletedAt === null) {
                $events[] = BulkEventEnum::DELETING;
            }
        }

        return $this->fireModelEventsFeature->handle($model, $scenarioConfig->events, $events);
    }

    /**
     * @param Collection $collection
     * @param BulkScenarioConfig $scenarioConfig
     * @return Collection
     */
    private function prepareModels(Collection $collection, BulkScenarioConfig $scenarioConfig): Collection
    {
        return $collection
            ->filter(
                fn (BulkModel $model) => $this->fireModelEvents($model, $scenarioConfig)
            )
            ->filter(
                fn (BulkModel $model) => empty($this->getDirtyAttributesFeature->handle($model)) === false
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
        $result = $this->getDirtyAttributesFeature->handle($model);

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
     * @param BulkScenarioConfig $scenarioConfig
     * @param array $sets
     * @return void
     * @throws JsonException
     */
    private function prepareBuildersSets(
        BulkModel $model,
        BulkScenarioConfig $scenarioConfig,
        array &$sets,
    ): void {
        $attributes = $this->getDirtyAttributes($model, $scenarioConfig->updateAttributes);

        if (empty($attributes)) {
            return;
        }

        $row = $this->arrayToScalarArrayConverter->handle($scenarioConfig->dateFields, $attributes);

        foreach ($row as $key => $value) {
            if (in_array($key, $scenarioConfig->uniqueAttributes, true) === false) {
                $valueHash = hash('crc32c', $value . ':' . gettype($value));

                $sets[$key] ??= [];
                $sets[$key][$valueHash] ??= ['value' => $value, 'filters' => []];
                $sets[$key][$valueHash]['filters'][] = $this->getUniqueAttributeValues($model, $scenarioConfig->uniqueAttributes);
            }
        }
    }

    /**
     * @param UpdateBuilder $builder
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

    private function runDeletingOrRestoringCallbacks(
        BulkModel $eloquent,
        BulkScenarioConfig $scenarioConfig,
        Collection $collection
    ): Collection {
        if ($scenarioConfig->deletedAtColumn === null) {
            return $collection;
        }

        if ($scenarioConfig->deletingCallback === null && $scenarioConfig->restoringCallback === null) {
            return $collection;
        }

        $groups = $collection->groupBy(
            function (BulkModel $model) use ($scenarioConfig): string {
                $actualDeletedAt = $model->getAttribute($scenarioConfig->deletedAtColumn);
                $originalDeletedAt = $model->getOriginal($scenarioConfig->deletedAtColumn);

                if ($actualDeletedAt === null && $originalDeletedAt !== null) {
                    return 'restoring';
                }

                if ($actualDeletedAt !== null && $originalDeletedAt === null) {
                    return 'deleting';
                }

                return 'common';
            }
        );

        if ($scenarioConfig->deletingCallback !== null
            && $groups->has('deleting')
            && $groups->get('deleting')->isNotEmpty()
        ) {
            $groups->put(
                'deleting',
                $scenarioConfig->deletingCallback->handle($groups->get('deleting')) ?? $groups->get('deleting')
            );
        }

        if ($scenarioConfig->restoringCallback !== null
            && $groups->has('restoring')
            && $groups->get('restoring')->isNotEmpty()
        ) {
            $groups->put(
                'restoring',
                $scenarioConfig->restoringCallback->handle($groups->get('restoring')) ?? $groups->get('restoring')
            );
        }

        return $eloquent->newCollection($groups->collapse()->all());
    }

    private function canProcessCollection(BulkScenarioConfig $scenarioConfig): bool
    {
        $result = $scenarioConfig->updatingCallback === null
            && $scenarioConfig->savingCallback === null;

        if ($result && $scenarioConfig->deletedAtColumn !== null) {
            return $scenarioConfig->deletingCallback === null
                && $scenarioConfig->restoringCallback === null;
        }

        return $result;
    }
}
