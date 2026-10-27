<?php

namespace Lapaliv\BulkUpsert\Builders;

use Carbon\Carbon;
use Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;

/**
 * Builds the INSERT statement for the given models.
 *
 * @internal
 */
class InsertBuilderFactory
{
    /**
     * The name of the column which keeps the creation time.
     *
     * @var string|null
     */
    private ?string $createdAtColumn = null;

    /**
     * The name of the column which keeps the time of the last update.
     *
     * @var string|null
     */
    private ?string $updatedAtColumn = null;

    /**
     * The formatted value for the creation time column.
     *
     * @var string|null
     */
    private ?string $createdAt = null;

    /**
     * The formatted value for the update time column.
     *
     * @var string|null
     */
    private ?string $updatedAt = null;

    /**
     * Create a new factory instance.
     *
     * @param \Lapaliv\BulkUpsert\Converters\AttributesToScalarArrayConverter $scalarArrayConverter
     */
    public function __construct(
        private readonly AttributesToScalarArrayConverter $scalarArrayConverter
    )
    {
        //
    }

    /**
     * Create an instance of the InsertBuilder and fill in the values using the provided models.
     *
     * Returns null when there are no rows or when the models do not have any attribute.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param bool $ignore Whether the rows with a unique conflict have to be skipped.
     * @param array $dateFields The date format of each date column.
     * @param string[] $selectColumns The columns which have to be returned by the statement.
     *
     * @return \Lapaliv\BulkUpsert\Builders\InsertBuilder|null
     * @throws \DateMalformedStringException
     */
    public function handle(
        BulkAccumulationEntity $data,
        bool $ignore,
        array $dateFields,
        array $selectColumns,
    ): ?InsertBuilder
    {
        if (!$data->hasRows()) {
            return null;
        }

        $eloquent = $data->getFirstModel();
        $result = new InsertBuilder();
        $result->setTable($eloquent->getTable())->onConflictDoNothing($ignore);
        $columns = [];

        $this->createdAtColumn = $eloquent->getCreatedAtColumn();
        $this->updatedAtColumn = $eloquent->getUpdatedAtColumn();

        $this->createdAt = null;
        $this->updatedAt = null;

        if ($eloquent->usesTimestamps()) {
            // The same value and format as `Model::save()` writes; a column whose constant is
            // `null` does not exist and gets no value.
            $now = $eloquent->freshTimestampString();

            $this->createdAt = $this->createdAtColumn === null ? null : $now;
            $this->updatedAt = $this->updatedAtColumn === null ? null : $now;
        }

        foreach ($data->getRows() as $row) {
            $array = $this->convertModelToArray($row, $columns, $dateFields);

            /** @psalm-suppress MixedArgumentTypeCoercion */
            $result->addValue($array);
        }

        if (empty($columns)) {
            return null;
        }

        $result->setColumns($columns);

        if (!empty($selectColumns)) {
            $result->setSelect($selectColumns);
        }

        return $result;
    }

    /**
     * Convert all the attributes of the model to an array.
     *
     * The keys of the converted attributes are added to the list of the columns,
     * so that the caller gets the columns of all the rows together.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity $row
     * @param array<string, string> $columns The collected columns of the statement.
     * @param array $dateFields The date format of each date column.
     *
     * @return array<string, mixed>
     * @throws \DateMalformedStringException
     */
    private function convertModelToArray(
        BulkAccumulationItemEntity $row,
        array &$columns,
        array $dateFields,
    ): array
    {
        /** @psalm-suppress MixedArgumentTypeCoercion */
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
     * The values are only filled in when the model uses the timestamps and when
     * the row does not have its own value yet.
     *
     * @param array<string, mixed> $result The converted attributes of the row.
     * @param array<string, string> $columns The collected columns of the statement.
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity $row
     *
     * @return void
     */
    private function freshTimestamps(
        array &$result,
        array &$columns,
        BulkAccumulationItemEntity $row,
    ): void
    {
        if ($row->getModel()->usesTimestamps()) {
            // Laravel lets a model drop one of the columns by setting its constant to `null`;
            // the other one is still filled.
            if ($this->createdAtColumn !== null) {
                $columns[$this->createdAtColumn] ??= $this->createdAtColumn;
                /** @psalm-suppress MixedAssignment */
                $result[$this->createdAtColumn] ??= $this->createdAt;
            }

            if ($this->updatedAtColumn !== null) {
                $columns[$this->updatedAtColumn] ??= $this->updatedAtColumn;
                /** @psalm-suppress MixedAssignment */
                $result[$this->updatedAtColumn] ??= $this->updatedAt;
            }
        }
    }
}
