<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses\Where;

/**
 * @internal
 */
interface BuilderWhere
{
    public function getBoolean(): string;
}
