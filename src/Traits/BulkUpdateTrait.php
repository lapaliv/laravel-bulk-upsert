<?php

namespace Lapaliv\BulkUpsert\Traits;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Support\BulkCallback;

trait BulkUpdateTrait
{
    private ?BulkCallback $updatingCallback = null;
    private ?BulkCallback $updatedCallback = null;

    /**
     * @param null|callable(Collection<scalar, BulkModel>): Collection<scalar, BulkModel>|null|void $callback
     * @return $this
     */
    public function onUpdating(?callable $callback): static
    {
        $this->updatingCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }

    /**
     * @param null|callable(Collection<scalar, BulkModel>): Collection<scalar, BulkModel>|null|void $callback
     * @return $this
     */
    public function onUpdated(?callable $callback): static
    {
        $this->updatedCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }
}
