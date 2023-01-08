<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkDatabaseDriver;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

class BulkUpdateFeature
{
    public function __construct(
        private BulkSelectExistingRowsFeature $selectExistingRowsFeature,
        private BulkFireModelEventsFeature $fireModelEventsFeature,
        private BulkFreshTimestampsFeature $freshTimestampsFeature,
        private BulkConvertAttributesToScalarArrayFeature $convertAttributesToScalarArrayFeature,
        private BulkGetDriverFeature $getDriverFeature,
    )
    {
        //
    }

    /**
     * @param BulkModel $model
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @param string[] $selectColumns
     * @param string[] $dateFields
     * @param string[] $events
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null $updatingCallback
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null $updatedCallback
     * @param BulkModel[] $models
     * @return void
     */
    public function handle(
        BulkModel $model,
        array $uniqueAttributes,
        ?array $updateAttributes,
        array $selectColumns,
        array $dateFields,
        array $events,
        ?callable $updatingCallback,
        ?callable $updatedCallback,
        array $models,
    ): void
    {
        $existingModels = $this->selectExistingRowsFeature->handle(
            $model,
            $uniqueAttributes,
            $updateAttributes,
            $selectColumns,
            $models
        );

        if ($existingModels->isEmpty()) {
            return;
        }

        if ($updatingCallback !== null) {
            $existingModels = $updatingCallback($existingModels) ?? $existingModels;
        }

        $uniqueAttributes = [$model->getKeyName()];

        $preparedRows = $this->preparingModels(
            $existingModels,
            $dateFields,
            $uniqueAttributes,
            $updateAttributes,
            $events,
        );

        if (empty($preparedRows)) {
            return;
        }

        $driver = $this->getDriver(
            $model,
            $uniqueAttributes,
            $selectColumns,
            $preparedRows,
        );

        $driver->update();

        $this->finalStep($existingModels, $events);

        if ($updatedCallback !== null) {
            $updatedCallback($existingModels);
        }
    }

    /**
     * @param Collection<BulkModel> $models
     * @param string[] $dateFields
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @param string[] $events
     * @return scalar[][]
     */
    private function preparingModels(
        Collection $models,
        array $dateFields,
        array $uniqueAttributes,
        ?array $updateAttributes,
        array $events,
    ): array
    {
        $rows = [];

        foreach ($models as $model) {
            $firing = $this->fireModelEventsFeature->handle(
                $model,
                $events,
                [BulkEventEnum::SAVING, BulkEventEnum::UPDATING]
            );

            if ($firing === false) {
                continue;
            }

            $this->freshTimestampsFeature->handle($model);

            $attributes = $model->getDirty();

            if (empty($updateAttributes) === false) {
                $attributes = array_filter(
                    $attributes,
                    static function (mixed $value, string $key) use ($updateAttributes, $uniqueAttributes): bool {
                        return in_array($key, $updateAttributes, true)
                            || in_array($key, $uniqueAttributes, true);
                    },
                    ARRAY_FILTER_USE_BOTH
                );
            }

            if (empty($attributes)) {
                continue;
            }

            // return unique attributes
            foreach ($uniqueAttributes as $attribute) {
                $attributes[$attribute] ??= $model->getAttribute($attribute);
            }

            $rows[] = $this->convertAttributesToScalarArrayFeature->handle($dateFields, $attributes);
        }

        return $rows;
    }

    /**
     * @param BulkModel $model
     * @param string[] $uniqueAttributes
     * @param string[] $selectColumns
     * @param scalar[][] $rows
     * @return BulkDatabaseDriver
     */
    private function getDriver(
        BulkModel $model,
        array $uniqueAttributes,
        array $selectColumns,
        array $rows,
    ): BulkDatabaseDriver
    {
        return $this->getDriverFeature->handle(
            $model,
            $rows,
            $uniqueAttributes,
            $selectColumns
        );
    }

    /**
     * @param Collection<BulkModel> $existingModels
     * @param string[] $events
     * @return void
     */
    private function finalStep(Collection $existingModels, array $events): void
    {
        $existingModels->map(
            function (BulkModel $model) use ($events): void {
                $model->syncChanges();

                if ($model->isDirty()) {
                    $this->fireModelEventsFeature->handle(
                        $model,
                        $events,
                        [BulkEventEnum::UPDATED, BulkEventEnum::SAVED]
                    );
                } else {
                    $this->fireModelEventsFeature->handle(
                        $model,
                        $events,
                        [BulkEventEnum::SAVED]
                    );
                }

                $model->syncOriginal();
            }
        );
    }
}
