<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses\Where;

use Closure;

/**
 * A nested group of conditions which is filled in by the given closure.
 *
 * @internal
 *
 * @psalm-api
 */
readonly class BuilderWhereCallback implements BuilderWhere
{
    /**
     * Create a new nested group of conditions.
     *
     * @param \Closure $callback The closure which fills in the nested builder.
     * @param string $boolean The keyword which joins this group with the previous one.
     */
    public function __construct(
        private Closure $callback,
        public string $boolean
    )
    {
        //
    }

    public function getCallback(): Closure
    {
        return $this->callback;
    }

    public function getBoolean(): string
    {
        return $this->boolean;
    }
}
