<?php

namespace Lapaliv\BulkUpsert\Support;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;

/**
 * Collects the rows which `*AndReturn()` gives back.
 *
 * The result does not depend on the events: the scenarios add the rows themselves, so
 * `disableEvents()` or `setEvents()` cannot empty it, and no temporary listener is left
 * behind.
 *
 * The rows, not their models, are collected: a scenario may replace the model of a row
 * (e.g. with the one read back from the database, or between the two parts of an upsert),
 * so the model is taken only at the end. A row added twice is kept once, at its first
 * position.
 *
 * @internal
 */
final class ReturnedModels
{
    /**
     * The rows keyed by their object id.
     *
     * @var array<int, \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity>
     */
    private array $rows = [];

    /**
     * Add the row to the result.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity $row
     *
     * @return void
     */
    public function add(BulkAccumulationItemEntity $row): void
    {
        $this->rows[spl_object_id($row)] ??= $row;
    }

    /**
     * Get the current models of the collected rows in the order the rows were added.
     *
     * @return list<\Illuminate\Database\Eloquent\Model>
     */
    public function all(): array
    {
        return array_values(array_map(
            static fn (BulkAccumulationItemEntity $row): Model => $row->getModel(),
            $this->rows
        ));
    }
}
