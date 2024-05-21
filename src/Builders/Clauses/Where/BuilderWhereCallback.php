<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses\Where;

use Closure;

/**
 * @internal
 *
 * @psalm-api
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
