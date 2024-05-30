<?php

namespace Lapaliv\BulkUpsert\Features;

use Carbon\Carbon;
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

    /**
     * Create an instance of the InsertBuilder and fill in the values using the provided models.
     *
     * @param BulkAccumulationEntity $data
     * @param bool $ignore
     * @param array $dateFields
     * @param array $selectColumns
     *
     * @return InsertBuilder|null
     */
    public function handle(
        BulkAccumulationEntity $data,
        bool $ignore,
        array $dateFields,
        array $selectColumns,
    ): ?InsertBuilder {
        if (!$data->hasRows()) {
            return null;
        }

        $eloquent = $data->getFirstModel();
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

        foreach ($data->getRows() as $row) {
            $array = $this->convertModelToArray($row, $columns, $dateFields);
            $result->addValue($array);
        }

        if (empty($columns)) {
            return null;
        }

        $result->columns($columns);

        if (!empty($selectColumns)) {
            $result->select($selectColumns);
        }

        return $result;
    }

    /**
     * Convert all the attributes of the model to an array.
     *
     * @param BulkAccumulationItemEntity $row
     * @param array $columns
     * @param array $dateFields
     *
     * @return array
     */
    private function convertModelToArray(
        BulkAccumulationItemEntity $row,
        array &$columns,
        array $dateFields,
    ): array {
        $result = $this->scalarArrayConverter->handle(
            $row->getModel(),
            $row->getModel()->getAttributes(),
            $dateFields,
        );

        foreach (array_keys($result) as $key) {
            $columns[$key] = $key;
        }

        $this->freshTimestamps($result, $columns, $row);

        return $result;
    }

    /**
     * Fill in timestamp columns for each model.
     *
     * @param array $result
     * @param array $columns
     * @param BulkAccumulationItemEntity $row
     *
     * @return void
     */
    private function freshTimestamps(
        array &$result,
        array &$columns,
        BulkAccumulationItemEntity $row,
    ): void {
        if ($row->getModel()->usesTimestamps()) {
            $columns[$this->createdAtColumn] ??= $this->createdAtColumn;
            $columns[$this->updatedAtColumn] ??= $this->updatedAtColumn;

            $result[$this->updatedAtColumn] ??= $this->updatedAt;
            $result[$this->createdAtColumn] ??= $this->createdAt;
        }
    }
}
