<?php

namespace Lapaliv\BulkUpsert\Traits;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Support\BulkCallback;

trait BulkSavedTrait
{
    private ?BulkCallback $savedCallback = null;

    /**
     * @param callable(Collection<scalar, BulkModel>): Collection<scalar, BulkModel>|null $callback
     * @return $this
     */
    public function onSaved(?callable $callback): static
    {
        $this->savedCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }
}
