<?php

namespace Lapaliv\BulkUpsert\Features;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Expression;
use Lapaliv\BulkUpsert\BulkDriverManager;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\DriverManager;
use Lapaliv\BulkUpsert\Converters\ArrayOfObjectToScalarArraysConverter;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Support\BulkCallback;

class InsertFeature
{
    public function __construct(
        private ArrayOfObjectToScalarArraysConverter $arrayOfObjectToScalarArraysConverter,
        private AlignFieldsFeature $alignFieldsFeature,
        private DriverManager $driverManager,
        private FillWasRecentlyCreatedFeature $fillWasRecentlyCreatedFeature,
        private PrepareInsertBuilderFeature $prepareInsertBuilderFeature,
        private SelectExistingRowsFeature $selectExistingRowsFeature,
        private FinishSaveFeature $finishSaveFeature,
    )
    {
        //
    }

    /**
     * @param BulkModel $eloquent
     * @param string[] $uniqueAttributes
     * @param string[] $selectColumns
     * @param string[] $dateFields
     * @param string[] $events
     * @param bool $ignore
     * @param BulkCallback|null $creatingCallback
     * @param BulkCallback|null $createdCallback
     * @param BulkCallback|null $savedCallback
     * @param Collection $collection
     * @return void
     */
    public function handle(
        BulkModel $eloquent,
        array $uniqueAttributes,
        array $selectColumns,
        array $dateFields,
        array $events,
        bool $ignore,
        ?BulkCallback $creatingCallback,
        ?BulkCallback $createdCallback,
        ?BulkCallback $savedCallback,
        Collection $collection,
    ): void
    {
        if ($collection->isEmpty()) {
            return;
        }

        // there aren't any events and callbacks
        if ($creatingCallback === null
            && $createdCallback === null
            && $savedCallback === null
            && empty($events)
        ) {
            $this->driverManager->getForModel($eloquent)
                ->simpleInsert(
                    $eloquent->newQuery(),
                    $this->alignFieldsFeature->handle(
                        $this->arrayOfObjectToScalarArraysConverter->handle($collection),
                        new Expression('default')
                    )
                );

            return;
        }

        $startedAt = Carbon::now();

        $builder = $this->prepareInsertBuilderFeature->handle(
            $eloquent,
            $collection,
            $dateFields,
            $events,
            $ignore,
            $creatingCallback
        );

        if ($builder === null) {
            return;
        }

        $driver = $this->driverManager->getForModel($eloquent);
        $lastInsertedId = $driver->insert($eloquent->getConnection(), $builder, $eloquent->getKeyName());

        // there aren't any callbacks and events after creating
        if ($createdCallback === null
            && $savedCallback === null
            && in_array(BulkEventEnum::CREATED, $events) === false
            && in_array(BulkEventEnum::SAVED, $events) === false
        ) {
            return;
        }

        $collection = $this->selectExistingRowsFeature->handle(
            $eloquent,
            $collection,
            $selectColumns,
            $uniqueAttributes,
        );

        $this->fillWasRecentlyCreatedFeature->handle($eloquent, $collection, $dateFields, $lastInsertedId, $startedAt);

        if ($createdCallback !== null) {
            $insertedModel = $collection->filter(
                fn(BulkModel $model) => $model->wasRecentlyCreated
            );

            if ($insertedModel->isNotEmpty()) {
                $createdCallback->handle($insertedModel);
            }
        }

        $this->finishSaveFeature->handle($eloquent, $collection, $eloquent->getConnection(), $driver, $events);

        $savedCallback?->handle($collection);
    }
}
