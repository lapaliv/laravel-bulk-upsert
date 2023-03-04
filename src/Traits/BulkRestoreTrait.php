<?php

namespace Lapaliv\BulkUpsert\Traits;

use Lapaliv\BulkUpsert\Support\BulkCallback;

trait BulkRestoreTrait
{
    private ?BulkCallback $restoringCallback = null;
    private ?BulkCallback $restoredCallback = null;

    public function onRestoring(?callable $callback): static
    {
        $this->restoringCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }

    public function onRestored(?callable $callback): static
    {
        $this->restoredCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }
}
