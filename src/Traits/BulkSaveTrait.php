<?php

namespace Lapaliv\BulkUpsert\Traits;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Support\BulkCallback;

trait BulkSaveTrait
{
    use BulkSavedTrait;

    private ?BulkCallback $savingCallback = null;

    /**
     * @param callable(Collection<scalar, BulkModel>): Collection<scalar, BulkModel>|null|void $callback
     * @return $this
     */
    public function onSaving(?callable $callback): static
    {
        $this->savingCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }
}
