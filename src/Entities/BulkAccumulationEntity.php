<?php

namespace Lapaliv\BulkUpsert\Entities;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Collections\BulkRowCollection;

/**
 * One chunk of the accumulated rows with the settings of its query.
 *
 * All the rows of the entity share the same unique attributes, so they can be
 * saved by a single query.
 *
 * @internal
 */
class BulkAccumulationEntity
{
    /**
     * The names of the attributes which make a row unique.
     *
     * @var string[]
     */
    private array $uniqueBy;

    /**
     * The accumulated rows of the chunk.
     *
     * @var \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity[]
     */
    private array $rows = [];

    /**
     * The names of the attributes which should be updated.
     *
     * @var string[]
     */
    private array $updateOnly = [];

    /**
     * The names of the attributes which should not be updated.
     *
     * @var string[]
     */
    private array $updateExcept = [];

    /**
     * Create a new chunk of the accumulated rows.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity[] $rows
     * @param string[] $uniqueBy
     * @param string[] $updateOnly
     * @param string[] $updateExcept
     *
     * @return void
     */
    public function __construct(
        array $rows = [],
        array $uniqueBy = [],
        array $updateOnly = [],
        array $updateExcept = [],
    )
    {
        $this->setRows($rows);
        $this->uniqueBy = $uniqueBy;
        $this->setUpdateOnly($updateOnly);
        $this->setUpdateExcept($updateExcept);
    }

    /**
     * Get the models which the saving process should not skip.
     *
     * The rows which are skipped by the creating or the updating process are left out.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function getNotSkippedSavingModels(): Collection
    {
        /** @var \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $result */
        $result = new Collection();

        foreach ($this->rows as $row) {
            if ($row->isSkipCreating() || $row->isSkipUpdating()) {
                continue;
            }

            $result->push($row->getModel());
        }

        return $result;
    }

    /**
     * Get the models which the updating process should not skip.
     *
     * The rows which are skipped by the saving or the updating process are left out.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function getNotSkippedUpdatingModels(): Collection
    {
        /** @var \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $result */
        $result = new Collection();

        foreach ($this->rows as $row) {
            if ($row->isSkipSaving() || $row->isSkipUpdating()) {
                continue;
            }

            $result->push($row->getModel());
        }

        return $result;
    }

    /**
     * Get the models which the deleting process should not skip.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function getNotSkippedDeletingModels(): Collection
    {
        /** @var \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> $result */
        $result = new Collection();

        foreach ($this->rows as $row) {
            if ($row->isSkipDeleting()) {
                continue;
            }

            $result->push($row->getModel());
        }

        return $result;
    }

    /**
     * Get the names of the attributes which make a row unique.
     *
     * @return string[]
     */
    public function getUniqueBy(): array
    {
        return $this->uniqueBy;
    }

    /**
     * Get the accumulated rows of the chunk.
     *
     * @return \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity[]
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * Set the accumulated rows of the chunk.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity[] $originals
     *
     * @return $this
     */
    public function setRows(array $originals): static
    {
        $this->rows = $originals;

        return $this;
    }


    /**
     * Add one row to the chunk.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity $original
     *
     * @return $this
     */
    public function addRow(BulkAccumulationItemEntity $original): static
    {
        $this->rows[] = $original;

        return $this;
    }

    /**
     * Get the names of the attributes which should be updated.
     *
     * @return string[]
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getUpdateOnly(): array
    {
        return $this->updateOnly;
    }

    /**
     * Set the names of the attributes which should be updated.
     *
     * @param string[] $updateOnly
     *
     * @return $this
     */
    public function setUpdateOnly(array $updateOnly): static
    {
        $this->updateOnly = $updateOnly;

        return $this;
    }

    /**
     * Get the names of the attributes which should not be updated.
     *
     * @return string[]
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getUpdateExcept(): array
    {
        return $this->updateExcept;
    }

    /**
     * Set the names of the attributes which should not be updated.
     *
     * @param string[] $updateExcept
     *
     * @return $this
     */
    public function setUpdateExcept(array $updateExcept): static
    {
        $this->updateExcept = $updateExcept;

        return $this;
    }

    /**
     * Remove the row with the given key from the chunk.
     *
     * @param int $key
     *
     * @return $this
     */
    public function unsetRow(int $key): static
    {
        unset($this->rows[$key]);

        return $this;
    }

    /**
     * Get the models of the rows as a collection of the model.
     *
     * The filter is applied to the collection when it is given.
     *
     * @param \Closure(\Illuminate\Database\Eloquent\Model): bool|null $filter
     *
     * @return \Illuminate\Support\Collection
     */
    public function getModels(?Closure $filter = null): Collection
    {
        if ($this->hasRows()) {
            $result = $this->getFirstModel()->newCollection(
                array_map(
                    fn(BulkAccumulationItemEntity $item) => $item->getModel(),
                    $this->getRows()
                )
            );

            $result = $filter !== null ? $result->filter($filter) : $result;

            return $result->values();
        }

        return new Collection();
    }

    /**
     * Get the rows as a collection of {@see \Lapaliv\BulkUpsert\Entities\BulkRow}.
     *
     * The filter gets the model of the row and decides whether the row is taken.
     *
     * @param \Closure(\Illuminate\Database\Eloquent\Model): bool|null $filter
     *
     * @return \Lapaliv\BulkUpsert\Collections\BulkRowCollection
     */
    public function getBulkRows(?Closure $filter = null): BulkRowCollection
    {
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $result */
        $result = new BulkRowCollection();

        foreach ($this->getRows() as $row) {
            if ($filter === null || $filter($row->getModel())) {
                $result->push(
                    new BulkRow($row->getModel(), $row->getOriginal(), $this->getUniqueBy())
                );
            }
        }

        return $result;
    }

    /**
     * Determine whether the chunk has any row.
     *
     * @return bool
     */
    public function hasRows(): bool
    {
        return !empty($this->getRows());
    }

    /**
     * Get the model of the first row of the chunk.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getFirstModel(): Model
    {
        /** @var \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity $item */
        $item = Arr::first($this->getRows());

        return $item->getModel();
    }
}
