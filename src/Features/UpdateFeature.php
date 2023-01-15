<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\DriverManager;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Support\BulkCallback;

class UpdateFeature
{
    public function __construct(
        private PrepareCollectionBeforeUpdatingFeature $prepareCollectionForUpdatingFeature,
        private FireModelEventsFeature $fireModelEventsFeature,
        private PrepareUpdateBuilderFeature $prepareUpdateBuilderFeature,
        private DriverManager $driverManager,
        private FinishSaveFeature $finishSaveFeature,
    )
    {
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
    ): void
    {
        if ($collection->isEmpty()) {
            return;
        }

        $collection = $this->prepareCollectionForUpdatingFeature->handle(
            $eloquent,
            $uniqueAttributes,
            $updateAttributes,
            $selectColumns,
            $collection,
        );

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

        if ($builder === null) {
            return;
        }

        $driver = $this->driverManager->getForModel($eloquent);
        $updateResult = $driver->update($eloquent->getConnection(), $builder);

        if ($updateResult === 0) {
            return;
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
