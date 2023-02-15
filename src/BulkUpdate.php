<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\BulkUpdateContract;
use Lapaliv\BulkUpsert\Converters\ArrayToCollectionConverter;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\GetBulkModelFeature;
use Lapaliv\BulkUpsert\Features\GetDateFieldsFeature;
use Lapaliv\BulkUpsert\Features\GetEloquentNativeEventNameFeature;
use Lapaliv\BulkUpsert\Features\KeyByFeature;
use Lapaliv\BulkUpsert\Features\SeparateIterableRowsFeature;
use Lapaliv\BulkUpsert\Scenarios\UpdateScenario;
use Lapaliv\BulkUpsert\Traits\BulkChunkTrait;
use Lapaliv\BulkUpsert\Traits\BulkEventsTrait;
use Lapaliv\BulkUpsert\Traits\BulkSaveTrait;
use Lapaliv\BulkUpsert\Traits\BulkSelectTrait;
use Lapaliv\BulkUpsert\Traits\BulkUpdateTrait;

class BulkUpdate implements BulkUpdateContract
{
    use BulkChunkTrait;
    use BulkEventsTrait;
    use BulkSelectTrait;
    use BulkSaveTrait;
    use BulkUpdateTrait;

    public function __construct(
        private UpdateScenario $updateFeature,
        private GetDateFieldsFeature $getDateFieldsFeature,
        private GetEloquentNativeEventNameFeature $getEloquentNativeEventNameFeature,
        private SeparateIterableRowsFeature $separateIterableRowsFeature,
        private ArrayToCollectionConverter $arrayToCollectionConverter,
        private GetBulkModelFeature $getBulkModelFeature,
        private KeyByFeature $keyByFeature,
    ) {
        $this->events = $this->getDefaultEvents();
    }

    /**
     * @param class-string<BulkModel>|BulkModel $model
     * @param iterable<scalar, mixed[]>|Collection<scalar, BulkModel>|array<scalar, mixed[]> $rows
     * @param string[]|null $uniqueAttributes
     * @param string[] $updateAttributes
     * @return void
     */
    public function update(
        string|BulkModel $model,
        iterable $rows,
        ?array $uniqueAttributes = null,
        ?array $updateAttributes = null,
    ): void {
        $model = $this->getBulkModelFeature->handle($model);
        $uniqueAttributes ??= [$model->getKeyName()];
        $selectColumns = $this->getSelectColumns($uniqueAttributes, $updateAttributes);
        $dateFields = $this->getDateFieldsFeature->handle($model);
        $events = $this->getIntersectEventsWithDispatcher($model, $this->getEloquentNativeEventNameFeature);

        $this->separateIterableRowsFeature->handle(
            $this->chunkSize,
            $rows,
            function (array $chunk) use ($model, $uniqueAttributes, $updateAttributes, $selectColumns, $dateFields, $events): void {
                $collection = $this->arrayToCollectionConverter->handle($model, $chunk);
                $collection = $model->newCollection(
                    array_values($this->keyByFeature->handle($collection, $uniqueAttributes))
                );

                $this->updateFeature->handle(
                    eloquent: $model,
                    uniqueAttributes: $uniqueAttributes,
                    updateAttributes: $updateAttributes,
                    selectColumns: $selectColumns,
                    dateFields: $dateFields,
                    events: $events,
                    updatingCallback: $this->updatingCallback,
                    updatedCallback: $this->updatedCallback,
                    savingCallback: $this->savingCallback,
                    savedCallback: $this->savedCallback,
                    collection: $this->chunkCallback?->handle($collection) ?? $collection,
                );
            }
        );
    }

    /**
     * @return string[]
     */
    protected function getDefaultEvents(): array
    {
        return [
            BulkEventEnum::UPDATING,
            BulkEventEnum::UPDATED,
            BulkEventEnum::SAVING,
            BulkEventEnum::SAVED,
        ];
    }
}
