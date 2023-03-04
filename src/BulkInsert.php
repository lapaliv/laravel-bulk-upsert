<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkInsertContract;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Converters\ArrayToCollectionConverter;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\GetBulkModelFeature;
use Lapaliv\BulkUpsert\Features\GetEloquentNativeEventNameFeature;
use Lapaliv\BulkUpsert\Features\KeyByFeature;
use Lapaliv\BulkUpsert\Features\SeparateIterableRowsFeature;
use Lapaliv\BulkUpsert\Scenarios\InsertScenario;
use Lapaliv\BulkUpsert\Traits\BulkChunkTrait;
use Lapaliv\BulkUpsert\Traits\BulkEventsTrait;
use Lapaliv\BulkUpsert\Traits\BulkInsertTrait;
use Lapaliv\BulkUpsert\Traits\BulkSavedTrait;
use Lapaliv\BulkUpsert\Traits\BulkScenarioConfigTrait;
use Lapaliv\BulkUpsert\Traits\BulkSelectTrait;
use Lapaliv\BulkUpsert\Traits\BulkSoftDeleteTrait;

class BulkInsert implements BulkInsertContract
{
    use BulkInsertTrait;
    use BulkSelectTrait;
    use BulkEventsTrait;
    use BulkChunkTrait;
    use BulkSavedTrait;
    use BulkScenarioConfigTrait;
    use BulkSoftDeleteTrait;

    public function __construct(
        private InsertScenario $scenario,
        private ArrayToCollectionConverter $arrayToCollectionConverter,
        private SeparateIterableRowsFeature $separateIterableRowsFeature,
        private GetBulkModelFeature $getBulkModelFeature,
        private GetEloquentNativeEventNameFeature $getEloquentNativeEventNameFeature,
        private KeyByFeature $keyByFeature,
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
    public function insert(
        string|BulkModel $model,
        array $uniqueAttributes,
        iterable $rows,
        bool $ignore = false,
    ): void {
        $eloquent = $this->getBulkModelFeature->handle($model);
        $scenarioConfig = $this->getConfig($eloquent, $uniqueAttributes);
        $generator = $this->separateIterableRowsFeature->handle($this->chunkSize, $rows);

        foreach ($generator as $chunk) {
            $collection = $this->arrayToCollectionConverter->handle($eloquent, $chunk);
            $collection = $eloquent->newCollection(
                array_values($this->keyByFeature->handle($collection, $uniqueAttributes))
            );

            $this->scenario->handle(
                $eloquent,
                $this->chunkCallback?->handle($collection) ?? $collection,
                $scenarioConfig,
                $ignore,
            );
        }
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
            BulkEventEnum::DELETING,
            BulkEventEnum::DELETED,
        ];
    }
}
