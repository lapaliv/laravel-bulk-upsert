<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses\Where;

/**
 * @internal
 *
 * @psalm-api
 */
class BuilderWhereCondition
{
    public function __construct(
        public string $field,
        public string $operator,
        public mixed $value,
        public string $boolean
    ) {
        //
    }
}
