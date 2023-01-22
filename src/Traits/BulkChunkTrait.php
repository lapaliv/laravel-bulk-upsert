<?php

namespace Lapaliv\BulkUpsert\Traits;

use Lapaliv\BulkUpsert\Support\BulkCallback;

trait BulkChunkTrait
{
    private int $chunkSize = 100;

    private ?BulkCallback $chunkCallback = null;

    /**
     * @param int $size
     * @param null|callable(Collection<scalar, BulkModel> $chunk): Collection<scalar, BulkModel>|null|void $callback
     * @return $this
     */
    public function chunk(int $size = 100, ?callable $callback = null): static
    {
        $this->chunkSize = $size;
        $this->chunkCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }
}
