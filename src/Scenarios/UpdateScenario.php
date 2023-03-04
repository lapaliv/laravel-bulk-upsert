<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use Illuminate\Database\Eloquent\Collection;
use JsonException;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\DriverManager;
use Lapaliv\BulkUpsert\Entities\BulkScenarioConfig;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\DivideCollectionByExistingFeature;
use Lapaliv\BulkUpsert\Features\FinishSaveFeature;
use Lapaliv\BulkUpsert\Features\FireModelEventsFeature;
use Lapaliv\BulkUpsert\Features\PrepareCollectionBeforeUpdatingFeature;
use Lapaliv\BulkUpsert\Features\PrepareUpdateBuilderFeature;

class UpdateScenario
{
    public function __construct(
        private PrepareCollectionBeforeUpdatingFeature $prepareCollectionForUpdatingFeature,
        private FireModelEventsFeature $fireModelEventsFeature,
        private DivideCollectionByExistingFeature $divideCollectionByExistingFeature,
        private PrepareUpdateBuilderFeature $prepareUpdateBuilderFeature,
        private DriverManager $driverManager,
        private FinishSaveFeature $finishSaveFeature,
    ) {
        //
    }

    /**
     * @param BulkModel $eloquent
     * @param Collection $collection
     * @param BulkScenarioConfig $scenarioConfig
     * @return void
     * @throws JsonException
     */
    public function handle(
        BulkModel $eloquent,
        Collection $collection,
        BulkScenarioConfig $scenarioConfig,
    ): void {
        if ($collection->isEmpty()) {
            return;
        }

        $dividedRows = $this->divideCollectionByExistingFeature->handle(
            $eloquent,
            $collection,
            $scenarioConfig->uniqueAttributes,
            $scenarioConfig->selectColumns,
        );

        if ($dividedRows->existing->isEmpty()) {
            return;
        }

        $collection = $this->prepareCollectionForUpdatingFeature->handle(
            $eloquent,
            $scenarioConfig->uniqueAttributes,
            $scenarioConfig->updateAttributes,
            $dividedRows->existing,
            $collection,
        );
        unset($dividedRows);

        $builder = $this->prepareUpdateBuilderFeature->handle(
            $eloquent,
            $collection,
            $scenarioConfig->events,
            $scenarioConfig->uniqueAttributes,
            $scenarioConfig->updateAttributes,
            $scenarioConfig->dateFields,
            $scenarioConfig->updatingCallback,
            $scenarioConfig->savingCallback,
        );

        $driver = $this->driverManager->getForModel($eloquent);

        if ($builder !== null && empty($builder->getSets()) === false) {
            $driver->update($eloquent->getConnection(), $builder);
            unset($builder);
        }

        $scenarioConfig->updatedCallback?->handle(clone $collection);

        $collection->each(
            function (BulkModel $model) use ($scenarioConfig): void {
                $model->syncChanges();
                $this->fireModelEventsFeature->handle($model, $scenarioConfig->events, [BulkEventEnum::UPDATED]);
            }
        );

        $this->finishSaveFeature->handle(
            $eloquent,
            $collection,
            $eloquent->getConnection(),
            $driver,
            $scenarioConfig->events,
        );

        $scenarioConfig->savedCallback?->handle($collection);
    }
}
