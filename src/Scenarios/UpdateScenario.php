<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use Illuminate\Database\Eloquent\Collection;
use JsonException;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\DriverManager;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\DivideCollectionByExistingFeature;
use Lapaliv\BulkUpsert\Features\FinishSaveFeature;
use Lapaliv\BulkUpsert\Features\FireModelEventsFeature;
use Lapaliv\BulkUpsert\Features\PrepareCollectionBeforeUpdatingFeature;
use Lapaliv\BulkUpsert\Features\PrepareUpdateBuilderFeature;
use Lapaliv\BulkUpsert\Support\BulkCallback;

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
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @param string[] $selectColumns
     * @param string[] $dateFields
     * @param string[] $events
     * @param BulkCallback|null $updatingCallback
     * @param BulkCallback|null $updatedCallback
     * @param BulkCallback|null $savingCallback
     * @param BulkCallback|null $savedCallback
     * @param Collection<BulkModel> $collection
     * @return void
     * @throws JsonException
     */
    public function handle(
        BulkModel $eloquent,
        array $uniqueAttributes,
        ?array $updateAttributes,
        array $selectColumns,
        array $dateFields,
        array $events,
        ?BulkCallback $updatingCallback,
        ?BulkCallback $updatedCallback,
        ?BulkCallback $savingCallback,
        ?BulkCallback $savedCallback,
        Collection $collection,
    ): void {
        if ($collection->isEmpty()) {
            return;
        }

        $dividedRows = $this->divideCollectionByExistingFeature->handle(
            $eloquent,
            $collection,
            $uniqueAttributes,
            $selectColumns,
        );

        if ($dividedRows->existing->isEmpty()) {
            return;
        }

        $collection = $this->prepareCollectionForUpdatingFeature->handle(
            $eloquent,
            $uniqueAttributes,
            $updateAttributes,
            $dividedRows->existing,
            $collection,
        );
        unset($dividedRows);

        $builder = $this->prepareUpdateBuilderFeature->handle(
            $eloquent,
            $collection,
            $events,
            $uniqueAttributes,
            $updateAttributes,
            $dateFields,
            $updatingCallback,
            $savingCallback,
        );

        $driver = $this->driverManager->getForModel($eloquent);

        if ($builder !== null && empty($builder->getSets()) === false) {
            $driver->update($eloquent->getConnection(), $builder);
            unset($builder);
        }

        $updatedCallback?->handle(clone $collection);

        $collection->each(
            function (BulkModel $model) use ($events): void {
                $model->syncChanges();
                $this->fireModelEventsFeature->handle($model, $events, [BulkEventEnum::UPDATED]);
            }
        );

        $this->finishSaveFeature->handle($eloquent, $collection, $eloquent->getConnection(), $driver, $events);

        $savedCallback?->handle($collection);
    }
}
