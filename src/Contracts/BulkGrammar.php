<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;

/**
 * Builds the SQL text of the bulk queries for one database dialect.
 *
 * @internal
 */
interface BulkGrammar
{
    /**
     * Build the SQL text of the insert query.
     *
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return string
     */
    public function insert(InsertBuilder $builder): string;

    /**
     * Build the SQL text of the update query.
     *
     * @param \Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder $builder
     *
     * @return string
     */
    public function update(UpdateBulkBuilder $builder): string;

    /**
     * Get the bindings which the last built query needs.
     *
     * @return array
     */
    public function getBindings(): array;
}
