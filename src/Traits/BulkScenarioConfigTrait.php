<?php

namespace Lapaliv\BulkUpsert\Traits;

use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Entities\BulkScenarioConfig;
use Lapaliv\BulkUpsert\Features\GetEloquentNativeEventNamesFeature;

trait BulkScenarioConfigTrait
{
    /**
     * @param BulkModel $eloquent
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @return BulkScenarioConfig
     */
    private function getConfig(
        BulkModel $eloquent,
        array $uniqueAttributes,
        ?array $updateAttributes = null,
    ): BulkScenarioConfig {
        $deletedAtColumn = method_exists($eloquent, 'getDeletedAtColumn')
            ? $eloquent->getDeletedAtColumn()
            : null;

        return new BulkScenarioConfig(
            events: $this->getEloquentNativeEventNamesFeature()->handle($eloquent, $this->getEvents()),
            uniqueAttributes: $uniqueAttributes,
            updateAttributes: $updateAttributes,
            selectColumns: $this->getSelectColumns($eloquent, $uniqueAttributes, $updateAttributes),
            chunkSize: $this->chunkSize,
            dateFields: $this->getEloquentDateFields($eloquent, $deletedAtColumn),
            deletedAtColumn: $deletedAtColumn,
            chunkCallback: $this->chunkCallback,
            creatingCallback: $this->creatingCallback ?? null,
            createdCallback: $this->createdCallback ?? null,
            updatingCallback: $this->updatingCallback ?? null,
            updatedCallback: $this->updatedCallback ?? null,
            savingCallback: $this->savingCallback ?? null,
            savedCallback: $this->savedCallback,
            deletingCallback: $this->deletingCallback ?? null,
            deletedCallback: $this->deletedCallback ?? null,
            restoringCallback: $this->restoringCallback ?? null,
            restoredCallback: $this->restoredCallback ?? null,
        );
    }

    /**
     * @return array<string, string>
     */
    private function getEloquentDateFields(
        BulkModel $model,
        ?string $deletedAtColumn
    ): array {
        $result = [];

        foreach ($model->getDates() as $field) {
            $result[$field] = $model->getDateFormat();
        }

        if ($deletedAtColumn !== null) {
            $result[$deletedAtColumn] = $model->getDateFormat();
        }

        foreach ($model->getCasts() as $key => $value) {
            if (is_string($value) && preg_match('/^(date(?:time)?)(?::(.+?))?$/', $value, $matches)) {
                if ($matches[1] === 'date') {
                    $result[$key] = $matches[2] ?? 'Y-m-d';
                } else {
                    $result[$key] = $matches[2] ?? $model->getDateFormat();
                }
            }
        }

        return $result;
    }

    abstract protected function getEloquentNativeEventNamesFeature(): GetEloquentNativeEventNamesFeature;
}
