<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses\Where;

use Closure;

/**
 * @internal
 */
class BuilderWhereCallback
{
    public function __construct(
        public Closure $callback,
        public string $boolean
    ) {
        //
    }
}
