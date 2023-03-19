<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Collection;
use JsonException;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Contracts\BulkUpdateContract;
use Lapaliv\BulkUpsert\Converters\ArrayToCollectionConverter;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Features\GetBulkModelFeature;
use Lapaliv\BulkUpsert\Features\GetEloquentNativeEventNamesFeature;
use Lapaliv\BulkUpsert\Features\KeyByFeature;
use Lapaliv\BulkUpsert\Features\SeparateIterableRowsFeature;
use Lapaliv\BulkUpsert\Scenarios\UpdateScenario;
use Lapaliv\BulkUpsert\Traits\BulkChunkTrait;
use Lapaliv\BulkUpsert\Traits\BulkEventsTrait;
use Lapaliv\BulkUpsert\Traits\BulkRestoreTrait;
use Lapaliv\BulkUpsert\Traits\BulkSaveTrait;
use Lapaliv\BulkUpsert\Traits\BulkScenarioConfigTrait;
use Lapaliv\BulkUpsert\Traits\BulkSelectTrait;
use Lapaliv\BulkUpsert\Traits\BulkSoftDeleteTrait;
use Lapaliv\BulkUpsert\Traits\BulkUpdateTrait;

class BulkUpdate implements BulkUpdateContract
{
    use BulkChunkTrait;
    use BulkEventsTrait;
    use BulkSelectTrait;
    use BulkSaveTrait;
    use BulkUpdateTrait;
    use BulkScenarioConfigTrait;
    use BulkSoftDeleteTrait;
    use BulkRestoreTrait;

    public function __construct(
        private UpdateScenario $scenario,
        private GetEloquentNativeEventNamesFeature $getEloquentNativeEventNamesFeature,
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
     * @throws JsonException
     */
    public function update(
        string|BulkModel $model,
        iterable $rows,
        ?array $uniqueAttributes = null,
        ?array $updateAttributes = null,
    ): void {
        $eloquent = $this->getBulkModelFeature->handle($model);
        $uniqueAttributes ??= [$eloquent->getKeyName()];
        $scenarioConfig = $this->getConfig($eloquent, $uniqueAttributes, $updateAttributes);
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
            );
        }
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
