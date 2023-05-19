<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;

/**
 * @internal
 */
interface BulkGrammar
{
    public function insert(InsertBuilder $builder): string;

    public function update(UpdateBulkBuilder $builder): string;

    public function getBindings(): array;
}
