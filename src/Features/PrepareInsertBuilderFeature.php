<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter;
use Lapaliv\BulkUpsert\Entities\BulkScenarioConfig;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

class PrepareInsertBuilderFeature
{
    public function __construct(
        private FireModelEventsFeature $fireModelEventsFeature,
        private AttributesToScalarArrayConverter $arrayToScalarArrayConverter,
        private FreshTimestampsFeature $freshTimestampsFeature,
    ) {
        //
    }

    public function handle(
        BulkModel $eloquent,
        Collection $collection,
        BulkScenarioConfig $scenarioConfig,
        bool $ignore,
    ): ?InsertBuilder {
        $result = new InsertBuilder();
        $result->into($eloquent->getTable())
            ->onConflictDoNothing($ignore);

        if ($scenarioConfig->creatingCallback === null && $scenarioConfig->deletedCallback === null) {
            $this->fillInBuilderFromCollection(
                $result,
                $collection,
                $scenarioConfig
            );
        } else {
            $collection = $this->prepareModels($collection, $scenarioConfig);
            $collection = $scenarioConfig->creatingCallback?->handle($collection) ?? $collection;
            $collection = $this->runDeletingCallback($eloquent, $scenarioConfig, $collection);

            if ($collection->isEmpty()) {
                $result->reset();

                return null;
            }

            $this->fillInBuilderFromArray(
                $result,
                $this->convertCollectionToArray($collection, $scenarioConfig->dateFields)
            );
        }

        return $result;
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
            ->each(
                fn (BulkModel $model) => $this->freshTimestampsFeature->handle($model)
            );
    }

    private function fireModelEvents(BulkModel $model, BulkScenarioConfig $scenarioConfig): bool
    {
        $events = [BulkEventEnum::SAVING, BulkEventEnum::CREATING];
        $isDeleting = $scenarioConfig->deletedAtColumn !== null
            && $model->getAttribute($scenarioConfig->deletedAtColumn) !== null;

        if ($isDeleting) {
            $events[] = BulkEventEnum::DELETING;
        }

        return $this->fireModelEventsFeature->handle($model, $scenarioConfig->events, $events);
    }

    /**
     * @param Collection $collection
     * @param string[] $dateFields
     * @return array
     */
    private function convertCollectionToArray(Collection $collection, array $dateFields): array
    {
        return $collection
            ->transform(
                fn (BulkModel $model) => $this->arrayToScalarArrayConverter->handle(
                    $dateFields,
                    $model->getAttributes(),
                )
            )
            ->toArray();
    }

    private function fillInBuilderFromCollection(
        InsertBuilder $builder,
        Collection $collection,
        BulkScenarioConfig $scenarioConfig,
    ): void {
        $columns = [];

        foreach ($collection as $model) {
            if ($this->fireModelEvents($model, $scenarioConfig) === false) {
                continue;
            }

            $this->freshTimestampsFeature->handle($model);

            $row = $this->arrayToScalarArrayConverter->handle(
                $scenarioConfig->dateFields,
                $model->getAttributes(),
            );

            foreach ($row as $key => $value) {
                $columns[$key] = $key;
            }

            $builder->addValue($row);
        }

        $builder->columns($columns);
    }

    private function fillInBuilderFromArray(InsertBuilder $builder, array $rows): void
    {
        $columns = [];

        foreach ($rows as $row) {
            $builder->addValue($row);

            foreach ($row as $key => $value) {
                $columns[$key] = $key;
            }
        }

        $builder->columns($columns);
    }

    private function runDeletingCallback(
        BulkModel $eloquent,
        BulkScenarioConfig $scenarioConfig,
        Collection $collection,
    ): Collection {
        if ($scenarioConfig->deletingCallback === null) {
            return $collection;
        }

        $groups = $collection->groupBy(
            fn (BulkModel $model) => $model->getAttribute($scenarioConfig->deletedAtColumn) === null
                ? 'common'
                : 'deleting'
        );

        if ($groups->has('deleting') && $groups->get('deleting')->isNotEmpty()) {
            $groups->put(
                'deleting',
                $scenarioConfig->deletingCallback->handle($groups->get('deleting')) ?? $groups->get('deleting')
            );
        }

        return $eloquent->newCollection($groups->collapse()->all());
    }
}
