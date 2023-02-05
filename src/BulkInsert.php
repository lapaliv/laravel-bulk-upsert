<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkInsertContract;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Converters\ArrayToCollectionConverter;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\GetBulkModelFeature;
use Lapaliv\BulkUpsert\Features\GetDateFieldsFeature;
use Lapaliv\BulkUpsert\Features\GetEloquentNativeEventNameFeature;
use Lapaliv\BulkUpsert\Features\SeparateIterableRowsFeature;
use Lapaliv\BulkUpsert\Scenarios\InsertScenario;
use Lapaliv\BulkUpsert\Traits\BulkChunkTrait;
use Lapaliv\BulkUpsert\Traits\BulkEventsTrait;
use Lapaliv\BulkUpsert\Traits\BulkInsertTrait;
use Lapaliv\BulkUpsert\Traits\BulkSavedTrait;
use Lapaliv\BulkUpsert\Traits\BulkSelectTrait;

class BulkInsert implements BulkInsertContract
{
    use BulkInsertTrait;
    use BulkSelectTrait;
    use BulkEventsTrait;
    use BulkChunkTrait;
    use BulkSavedTrait;

    public function __construct(
        private InsertScenario $insertFeature,
        private GetDateFieldsFeature $getDateFieldsFeature,
        private ArrayToCollectionConverter $arrayToCollectionConverter,
        private SeparateIterableRowsFeature $separateIterableRowsFeature,
        private GetBulkModelFeature $getBulkModelFeature,
        private GetEloquentNativeEventNameFeature $getEloquentNativeEventNameFeature,
    ) {
        $this->events = $this->getDefaultEvents();
    }

    /**
     * @param class-string<BulkModel>|BulkModel $model
     * @param string[] $uniqueAttributes
     * @param iterable|Collection<scalar, BulkModel>|array<scalar, array[]> $rows
     * @param bool $ignore
     * @return void
     */
    public function insert(string|BulkModel $model, array $uniqueAttributes, iterable $rows, bool $ignore = false): void
    {
        $model = $this->getBulkModelFeature->handle($model);
        $selectColumns = $this->getSelectColumns($model);
        $dateFields = $this->getDateFieldsFeature->handle($model);
        $events = $this->getIntersectEventsWithDispatcher($model, $this->getEloquentNativeEventNameFeature);

        $this->separateIterableRowsFeature->handle(
            $this->chunkSize,
            $rows,
            function (array $chunk) use ($model, $uniqueAttributes, $ignore, $selectColumns, $dateFields, $events): void {
                $collection = $this->arrayToCollectionConverter->handle($model, $chunk);

                $this->insertFeature->handle(
                    eloquent: $model,
                    uniqueAttributes: $uniqueAttributes,
                    selectColumns: $selectColumns,
                    dateFields: $dateFields,
                    events: $events,
                    ignore: $ignore,
                    creatingCallback: $this->creatingCallback,
                    createdCallback: $this->createdCallback,
                    savedCallback: $this->savedCallback,
                    collection: $this->chunkCallback?->handle($collection) ?? $collection
                );
            }
        );
    }

    /**
     * @param class-string<BulkModel>|BulkModel $model
     * @param string[] $uniqueAttributes
     * @param iterable|Collection<scalar, BulkModel>|array<scalar, array[]> $rows
     * @return void
     */
    public function insertOrIgnore(string|BulkModel $model, array $uniqueAttributes, iterable $rows): void
    {
        $this->insert($model, $uniqueAttributes, $rows, true);
    }

    /**
     * @return string[]
     */
    protected function getSelectColumns(BulkModel $model): array
    {
        if (in_array('*', $this->selectColumns, true)) {
            return ['*'];
        }

        if ($model->getIncrementing()) {
            return in_array($model->getKeyName(), $this->selectColumns, true)
                ? $this->selectColumns
                : array_merge($this->selectColumns, [$model->getKeyName()]);
        }

        if ($model->usesTimestamps()) {
            return in_array($model->getCreatedAtColumn(), $this->selectColumns, true)
                ? $this->selectColumns
                : array_merge($this->selectColumns, [$model->getCreatedAtColumn()]);
        }

        return $this->selectColumns;
    }

    /**
     * @return string[]
     */
    protected function getDefaultEvents(): array
    {
        return [
            BulkEventEnum::CREATING,
            BulkEventEnum::CREATED,
            BulkEventEnum::SAVING,
            BulkEventEnum::SAVED,
        ];
    }
}
