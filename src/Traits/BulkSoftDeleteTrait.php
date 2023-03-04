<?php

namespace Lapaliv\BulkUpsert\Traits;

use Lapaliv\BulkUpsert\Support\BulkCallback;

trait BulkSoftDeleteTrait
{
    private ?BulkCallback $deletingCallback = null;
    private ?BulkCallback $deletedCallback = null;

    public function onDeleting(?callable $callback): static
    {
        $this->deletingCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }

    public function onDeleted(?callable $callback): static
    {
        $this->deletedCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }
}
