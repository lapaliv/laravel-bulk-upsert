<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses\Where;

use Closure;

class BuilderWhereCallback
{
    public function __construct(
        public Closure $callback,
        public string $boolean
    )
    {
        //
    }
}
