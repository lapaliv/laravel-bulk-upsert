<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses\Where;

class BuilderWhereIn
{
    public function __construct(
        public string $field,
        public array $values,
        public string $boolean
    )
    {
        //
    }
}
