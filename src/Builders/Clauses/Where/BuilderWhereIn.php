<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses\Where;

/**
 * @internal
 *
 * @psalm-api
 */
class BuilderWhereIn
{
    /**
     * @param string $field
     * @param scalar[] $values
     * @param string $boolean
     */
    public function __construct(
        public string $field,
        public array $values,
        public string $boolean
    ) {
        //
    }
}
