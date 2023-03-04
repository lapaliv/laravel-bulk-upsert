<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Expression;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\DriverManager;
use Lapaliv\BulkUpsert\Converters\CollectionToScalarArraysConverter;
use Lapaliv\BulkUpsert\Entities\BulkScenarioConfig;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\AlignFieldsFeature;
use Lapaliv\BulkUpsert\Features\FillWasRecentlyCreatedFeature;
use Lapaliv\BulkUpsert\Features\FinishSaveFeature;
use Lapaliv\BulkUpsert\Features\FireModelEventsFeature;
use Lapaliv\BulkUpsert\Features\FreshTimestampsFeature;
use Lapaliv\BulkUpsert\Features\PrepareInsertBuilderFeature;
use Lapaliv\BulkUpsert\Features\SelectExistingRowsFeature;

class InsertScenario
{
    public function __construct(
        private CollectionToScalarArraysConverter $collectionToScalarArraysConverter,
        private AlignFieldsFeature $alignFieldsFeature,
        private DriverManager $driverManager,
        private FillWasRecentlyCreatedFeature $fillWasRecentlyCreatedFeature,
        private PrepareInsertBuilderFeature $prepareInsertBuilderFeature,
        private SelectExistingRowsFeature $selectExistingRowsFeature,
        private FinishSaveFeature $finishSaveFeature,
        private FreshTimestampsFeature $freshTimestampsFeature,
        private FireModelEventsFeature $fireModelEventsFeature,
    ) {
        //
    }

    /**
     * @param BulkModel $eloquent
     * @param Collection<int, BulkModel> $collection
     * @param BulkScenarioConfig $scenarioConfig
     * @param bool $ignore
     * @return void
     */
    public function handle(
        BulkModel $eloquent,
        Collection $collection,
        BulkScenarioConfig $scenarioConfig,
        bool $ignore,
    ): void {
        if ($collection->isEmpty()) {
            return;
        }

        // there aren't any events and callbacks
        if ($this->canUseSimpleInsert($scenarioConfig)) {
            $this->simpleInsert($eloquent, $collection, $scenarioConfig->dateFields);

            return;
        }

        $startedAt = Carbon::now()->startOfSecond();

        $builder = $this->prepareInsertBuilderFeature->handle(
            $eloquent,
            $collection,
            $scenarioConfig,
            $ignore,
        );

        if ($builder === null) {
            return;
        }

        $driver = $this->driverManager->getForModel($eloquent);
        $lastInsertedId = $driver->insert(
            $eloquent->getConnection(),
            $builder,
            $eloquent->getIncrementing() ? $eloquent->getKeyName() : null,
        );
        unset($builder);

        // there aren't any callbacks and events after creating
        if ($this->needToSelect($scenarioConfig) === false) {
            return;
        }

        $collection = $this->selectExistingRowsFeature->handle(
            $eloquent,
            $collection,
            $scenarioConfig->selectColumns,
            $scenarioConfig->uniqueAttributes,
            $scenarioConfig->deletedAtColumn,
        );

        $this->fillWasRecentlyCreatedFeature->handle(
            $eloquent,
            $collection,
            $scenarioConfig->dateFields,
            $lastInsertedId,
            $startedAt,
        );
        unset($startedAt, $lastInsertedId);

        $collection->each(
            fn (BulkModel $model) => $this->fireModelEventsFeature->handle(
                $model,
                $scenarioConfig->events,
                $this->getEventsForSelectedRows($scenarioConfig)
            )
        );

        $this->runCreatedCallback($scenarioConfig, $collection);
        $this->runDeletedCallback($scenarioConfig, $collection);

        $this->finishSaveFeature->handle(
            $eloquent,
            $collection,
            $eloquent->getConnection(),
            $driver,
            $scenarioConfig->events,
        );

        $scenarioConfig->savedCallback?->handle($collection);
    }

    /**
     * @param BulkModel $eloquent
     * @param Collection $collection
     * @param string[] $dateFields
     * @return void
     */
    private function simpleInsert(BulkModel $eloquent, Collection $collection, array $dateFields): void
    {
        $collection->map(
            fn (BulkModel $model) => $this->freshTimestampsFeature->handle($model)
        );

        $this->driverManager->getForModel($eloquent)
            ->simpleInsert(
                $eloquent->newQuery(),
                $this->alignFieldsFeature->handle(
                    $this->collectionToScalarArraysConverter->handle($collection, $dateFields),
                    new Expression('default')
                )
            );
    }

    private function canUseSimpleInsert(BulkScenarioConfig $scenarioConfig): bool
    {
        $result = $scenarioConfig->creatingCallback === null
            && $scenarioConfig->createdCallback === null
            && $scenarioConfig->savedCallback === null
            && empty($scenarioConfig->events);

        if ($result && $scenarioConfig->deletedAtColumn !== null) {
            return $scenarioConfig->deletingCallback === null
                && $scenarioConfig->deletedCallback === null;
        }

        return $result;
    }

    private function needToSelect(BulkScenarioConfig $scenarioConfig): bool
    {
        $result = $scenarioConfig->createdCallback !== null
            || $scenarioConfig->savedCallback !== null
            || in_array(BulkEventEnum::CREATED, $scenarioConfig->events)
            || in_array(BulkEventEnum::SAVED, $scenarioConfig->events);

        if ($result === false && $scenarioConfig->deletedAtColumn !== null) {
            return $scenarioConfig->deletedCallback !== null
                || in_array(BulkEventEnum::DELETED, $scenarioConfig->events);
        }

        return $result;
    }

    private function getEventsForSelectedRows(BulkScenarioConfig $scenarioConfig): array
    {
        $result = [BulkEventEnum::CREATED];

        if ($scenarioConfig->deletedAtColumn !== null) {
            $result[] = BulkEventEnum::DELETED;
        }

        return $result;
    }

    private function runCreatedCallback(BulkScenarioConfig $scenarioConfig, Collection $collection): void
    {
        if ($scenarioConfig->createdCallback !== null) {
            $insertedModels = $collection->filter(
                fn (BulkModel $model) => $model->wasRecentlyCreated
            );

            if ($insertedModels->isNotEmpty()) {
                $scenarioConfig->createdCallback->handle($insertedModels);
            }

            unset($insertedModels);
        }
    }

    private function runDeletedCallback(BulkScenarioConfig $scenarioConfig, Collection $collection): void
    {
        if ($scenarioConfig->deletedAtColumn !== null && $scenarioConfig->deletedCallback !== null) {
            $deletedModels = $collection->filter(
                fn (BulkModel $model) => $model->getAttribute($scenarioConfig->deletedAtColumn) !== null
            );

            if ($deletedModels->isNotEmpty()) {
                $scenarioConfig->deletedCallback->handle($deletedModels);
            }

            unset($deletedModels);
        }
    }
}
