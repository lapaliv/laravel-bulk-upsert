<?php

namespace Lapaliv\BulkUpsert\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Collections\BulkRows;

/**
 * @internal
 */
class BulkAccumulationEntity
{
    /**
     * @var string[]
     *
     * @deprecated use `$this->getUniqueBy()`
     */
    public array $uniqueBy;

    /**
     * @var BulkAccumulationItemEntity[]
     *
     * @deprecated use `$this->getRows()`
     */
    public array $rows = [];

    /**
     * @var string[]
     *
     * @deprecated use `$this->getUpdateOnly()`
     */
    public array $updateOnly = [];

    /**
     * @var string[]
     *
     * @deprecated use `$this->getUpdateExcept()`
     */
    public array $updateExcept = [];

    /**
     * @param BulkAccumulationItemEntity[] $rows
     * @param string[] $uniqueBy
     */
    public function __construct(
        array $rows = [],
        array $uniqueBy = [],
        array $updateOnly = [],
        array $updateExcept = [],
    ) {
        $this->setRows($rows);
        $this->uniqueBy = $uniqueBy;
        $this->updateOnly = $updateOnly;
        $this->updateExcept = $updateExcept;
    }

    public function getNotSkippedModels(string $key = null): Collection
    {
        $result = new Collection();

        foreach ($this->rows as $row) {
            if ($key === null && ($row->skipCreating || $row->skipUpdating)) {
                continue;
            }

            if ($key !== null && $row->{$key}) {
                continue;
            }

            $result->push($row->model);
        }

        return $result;
    }

    /**
     * @return string[]
     */
    public function getUniqueBy(): array
    {
        return $this->uniqueBy;
    }

    /**
     * @return BulkAccumulationItemEntity[]
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * @param BulkAccumulationItemEntity[] $originals
     *
     * @return $this
     */
    public function setRows(array $originals): static
    {
        $this->rows = $originals;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getUpdateOnly(): array
    {
        return $this->updateOnly;
    }

    /**
     * @return string[]
     */
    public function getUpdateExcept(): array
    {
        return $this->updateExcept;
    }

    public function unsetRow(int $key): static
    {
        unset($this->rows[$key]);

        return $this;
    }

    public function getModels(?callable $filter = null): Collection
    {
        if ($this->hasRows()) {
            $result = $this->getFirstModel()->newCollection(
                array_map(
                    fn (BulkAccumulationItemEntity $item) => $item->getModel(),
                    $this->getRows()
                )
            );

            $result = $filter ? $result->filter($filter) : $result;

            return $result->values();
        }

        return new Collection();
    }

    public function getBulkRows(?callable $filter = null): BulkRows
    {
        $result = new BulkRows();

        foreach ($this->getRows() as $row) {
            if (!$filter || $filter($row->getModel())) {
                $result->push(
                    new BulkRow($row->getModel(), $row->getOriginal(), $this->getUniqueBy())
                );
            }
        }

        return $result;
    }

    public function hasRows(): bool
    {
        return !empty($this->getRows());
    }

    public function getFirstModel(): Model
    {
        /** @var BulkAccumulationItemEntity $item */
        $item = Arr::first($this->getRows());

        return $item->getModel();
    }
}
