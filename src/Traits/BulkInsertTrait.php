<?php

namespace Lapaliv\BulkUpsert\Traits;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Support\BulkCallback;

trait BulkInsertTrait
{
    private ?BulkCallback $creatingCallback = null;
    private ?BulkCallback $createdCallback = null;

    /**
     * @param null|callable(Collection<scalar, BulkModel>): Collection<scalar, BulkModel>|null|void $callback
     * @return $this
     */
    public function onCreating(?callable $callback): static
    {
        $this->creatingCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }

    /**
     * @param null|callable(Collection<scalar, BulkModel>): Collection<scalar, BulkModel>|null|void $callback
     * @return $this
     */
    public function onCreated(?callable $callback): static
    {
        $this->createdCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }
}
