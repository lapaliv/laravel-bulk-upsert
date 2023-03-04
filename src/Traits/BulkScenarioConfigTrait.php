<?php

namespace Lapaliv\BulkUpsert\Traits;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Entities\BulkScenarioConfig;

trait BulkScenarioConfigTrait
{
    /**
     * @param BulkModel $eloquent
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @param array $dateFields
     * @return BulkScenarioConfig
     */
    private function getConfig(
        BulkModel $eloquent,
        array $uniqueAttributes,
        ?array $updateAttributes,
        array $dateFields,
    ): BulkScenarioConfig {
        return new BulkScenarioConfig(
            events: $this->getIntersectEventsWithDispatcher($eloquent, $this->getEloquentNativeEventNameFeature),
            uniqueAttributes: $uniqueAttributes,
            updateAttributes: $updateAttributes,
            selectColumns: $this->getSelectColumns($eloquent, $uniqueAttributes, $updateAttributes),
            chunkSize: $this->chunkSize,
            dateFields: $dateFields,
            chunkCallback: $this->chunkCallback,
            creatingCallback: $this->creatingCallback ?? null,
            createdCallback: $this->createdCallback ?? null,
            updatingCallback: $this->updatingCallback ?? null,
            updatedCallback: $this->updatedCallback ?? null,
            savingCallback: $this->savingCallback ?? null,
            savedCallback: $this->savedCallback
        );
    }
}
