<?php

namespace Lapaliv\BulkUpsert\Traits;

use Lapaliv\BulkUpsert\Support\BulkCallback;

trait BulkRestoreTrait
{
    private ?BulkCallback $onRestoring = null;
    private ?BulkCallback $onRestored = null;

    public function onRestoring(?callable $callback): static
    {
        $this->onRestoring = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }

    public function onRestored(?callable $callback): static
    {
        $this->onRestored = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }
}
