<?php

namespace Lapaliv\BulkUpsert\Support;

use Closure;

class BulkCallback
{
    private Closure $target;

    public function __construct(callable $callback)
    {
        $this->target = is_callable($callback)
            ? Closure::fromCallable($callback)
            : $callback;
    }

    public function handle(...$args): mixed
    {
        return call_user_func($this->target, ...$args);
    }
}
