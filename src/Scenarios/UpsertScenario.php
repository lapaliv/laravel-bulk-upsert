<?php

namespace Lapaliv\BulkUpsert\Scenarios;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Features\MarkNonexistentRowsAsSkippedFeature;

/**
 * @internal
 */
class UpsertScenario
{
    public function __construct(
        private MarkNonexistentRowsAsSkippedFeature $markNonexistentRowsAsSkipped,
        private CreateScenario $createScenario,
        private UpdateScenario $updateScenario,
    ) {
        //
    }

    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        array $dateFields,
        array $selectColumns,
        ?string $deletedAtColumn,
        bool $withTrashed,
    ): void {
        if (empty($data->rows)) {
            return;
        }

        $this->markNonexistentRowsAsSkipped->handle($eloquent, $data, $selectColumns, $deletedAtColumn, $withTrashed);

        $this->create($eloquent, $data, $eventDispatcher, $dateFields, $selectColumns, $deletedAtColumn);

        $this->updateScenario->handle(
            $eloquent,
            $data,
            $eventDispatcher,
            $dateFields,
            $selectColumns,
            $deletedAtColumn,
            $withTrashed
        );
    }

    private function create(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        array $dateFields,
        array $selectColumns,
        ?string $deletedAtColumn,
    ): void {
        $accumulationEntity = new BulkAccumulationEntity($data->uniqueBy);

        foreach ($data->rows as $row) {
            if ($row->skipUpdating) {
                $accumulationEntity->rows[] = $row;
            }
        }

        if (empty($accumulationEntity->rows)) {
            return;
        }

        $this->createScenario->handle(
            $eloquent,
            $accumulationEntity,
            $eventDispatcher,
            ignore: true,
            dateFields: $dateFields,
            selectColumns: $selectColumns,
            deletedAtColumn: $deletedAtColumn,
        );

        foreach ($data->rows as $row) {
            if ($row->skipUpdating && !$row->model->wasRecentlyCreated) {
                $row->skipUpdating = false;
            } elseif ($row->skipUpdating) {
                $row->skipSaving = true;
            }
        }
    }
}
