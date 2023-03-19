<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

namespace Lapaliv\BulkUpsert;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\BulkUpsertContract;
use Lapaliv\BulkUpsert\Converters\ArrayToCollectionConverter;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\GetBulkModelFeature;
use Lapaliv\BulkUpsert\Features\GetEloquentNativeEventNamesFeature;
use Lapaliv\BulkUpsert\Features\KeyByFeature;
use Lapaliv\BulkUpsert\Features\SeparateIterableRowsFeature;
use Lapaliv\BulkUpsert\Scenarios\UpsertScenario;
use Lapaliv\BulkUpsert\Traits\BulkChunkTrait;
use Lapaliv\BulkUpsert\Traits\BulkEventsTrait;
use Lapaliv\BulkUpsert\Traits\BulkInsertTrait;
use Lapaliv\BulkUpsert\Traits\BulkRestoreTrait;
use Lapaliv\BulkUpsert\Traits\BulkSaveTrait;
use Lapaliv\BulkUpsert\Traits\BulkScenarioConfigTrait;
use Lapaliv\BulkUpsert\Traits\BulkSelectTrait;
use Lapaliv\BulkUpsert\Traits\BulkSoftDeleteTrait;
use Lapaliv\BulkUpsert\Traits\BulkUpdateTrait;
use stdClass;

class BulkUpsert implements BulkUpsertContract
{
    use BulkChunkTrait;
    use BulkSelectTrait;
    use BulkEventsTrait;
    use BulkSaveTrait;
    use BulkInsertTrait;
    use BulkUpdateTrait;
    use BulkScenarioConfigTrait;
    use BulkSoftDeleteTrait;
    use BulkRestoreTrait;

    public function __construct(
        private UpsertScenario $scenario,
        private GetBulkModelFeature $getBulkModelFeature,
        private GetEloquentNativeEventNamesFeature $getEloquentNativeEventNamesFeature,
        private SeparateIterableRowsFeature $separateIterableRowsFeature,
        private ArrayToCollectionConverter $arrayToCollectionConverter,
        private KeyByFeature $keyByFeature,
    ) {
        $this->setEvents($this->getDefaultEvents());
    }

    /**
     * @param class-string<BulkModel>|BulkModel $model
     * @param iterable<mixed[][]|BulkModel|stdClass[][]> $rows
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @return void
     */
    public function upsert(
        string|BulkModel $model,
        iterable $rows,
        array $uniqueAttributes,
        ?array $updateAttributes = null,
    ): void {
        $eloquent = $this->getBulkModelFeature->handle($model);
        $this->scenario->setEloquent($eloquent);
        $scenarioConfig = $this->getConfig($eloquent, $uniqueAttributes, $updateAttributes);
        $generator = $this->separateIterableRowsFeature->handle($this->chunkSize, $rows);

        foreach ($generator as $chunk) {
            $collection = $this->arrayToCollectionConverter->handle($eloquent, $chunk);
            $collection = $eloquent->newCollection(
                array_values($this->keyByFeature->handle($collection, $scenarioConfig->uniqueAttributes))
            );
            $collection = $this->chunkCallback?->handle($collection) ?? $collection;

            $this->scenario
                ->push($eloquent, $collection, $scenarioConfig)
                ->insert($scenarioConfig)
                ->update($scenarioConfig);
        }

        $this->scenario
            ->insert($scenarioConfig, force: true)
            ->update($scenarioConfig, force: true);
    }

    /**
     * @return string[]
     */
    protected function getDefaultEvents(): array
    {
        return [
            BulkEventEnum::CREATING,
            BulkEventEnum::CREATED,
            BulkEventEnum::UPDATING,
            BulkEventEnum::UPDATED,
            BulkEventEnum::SAVING,
            BulkEventEnum::SAVED,
            BulkEventEnum::DELETING,
            BulkEventEnum::DELETED,
            BulkEventEnum::RESTORING,
            BulkEventEnum::RESTORED,
        ];
    }

    protected function getEloquentNativeEventNamesFeature(): GetEloquentNativeEventNamesFeature
    {
        return $this->getEloquentNativeEventNamesFeature;
    }
}
