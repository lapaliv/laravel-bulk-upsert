<?php

namespace Lapaliv\BulkUpsert\Features;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;

/**
 * @internal
 */
class GetInsertBuilderFeature
{
    private string $createdAtColumn;
    private string $updatedAtColumn;
    private string $createdAt;
    private string $updatedAt;

    public function __construct(
        private AttributesToScalarArrayConverter $scalarArrayConverter
    ) {
        //
    }

    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        bool $ignore,
        array $dateFields,
        ?string $deletedAtColumn,
    ): ?InsertBuilder {
        $result = new InsertBuilder();
        $result->into($eloquent->getTable())->onConflictDoNothing($ignore);
        $columns = [];

        $this->createdAtColumn = $eloquent->getCreatedAtColumn();
        $this->updatedAtColumn = $eloquent->getUpdatedAtColumn();

        if ($eloquent->usesTimestamps()) {
            $now = Carbon::now();
            $this->createdAt = $now->format(
                $dateFields[$this->createdAtColumn] ?? 'Y-m-d H:i:s'
            );
            $this->updatedAt = $now->format(
                $dateFields[$this->updatedAtColumn] ?? 'Y-m-d H:i:s'
            );
        }

        foreach ($data->rows as $row) {
            if ($row->skipCreating) {
                continue;
            }

            $columns = [];
            $array = $this->convertModelToArray($row, $columns, $dateFields, $deletedAtColumn);
            $result->addValue($array);
        }

        if (empty($columns)) {
            return null;
        }

        return $result->columns($columns);
    }

    private function convertModelToArray(
        BulkAccumulationItemEntity $row,
        array &$columns,
        array $dateFields,
        ?string $deletedAtColumn,
    ): array {
        $result = $this->scalarArrayConverter->handle(
            $row->model,
            $row->model->getAttributes(),
            $dateFields,
        );

        foreach ($result as $key => $value) {
            $columns[$key] = $key;
        }

        $this->freshTimestamps($result, $columns, $row, $deletedAtColumn);

        return $result;
    }

    private function freshTimestamps(
        array &$result,
        array &$columns,
        BulkAccumulationItemEntity $row,
        ?string $deletedAtColumn,
    ): void {
        if ($row->isDeleting && $row->skipDeleting) {
            $result[$deletedAtColumn] = null;
        }

        if ($row->model->usesTimestamps()) {
            if (!isset($columns[$this->createdAtColumn])) {
                $columns[$this->createdAtColumn] = $this->createdAtColumn;
                $result[$this->createdAtColumn] = $this->createdAt;
            }

            if (!isset($columns[$this->updatedAtColumn])) {
                $columns[$this->updatedAtColumn] = $this->updatedAtColumn;
                $result[$this->updatedAtColumn] = $this->updatedAt;
            }
        }
    }
}
