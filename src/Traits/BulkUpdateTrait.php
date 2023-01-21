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
     * @param callable(Collection<scalar, BulkModel>): Collection<scalar, BulkModel> $callback
     * @return $this
     */
    public function onUpdating(?callable $callback): static
    {
        $this->updatingCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }

    /**
     * @param callable(Collection<scalar, BulkModel>): Collection<scalar, BulkModel> $callback
     * @return $this
     */
    public function onUpdated(?callable $callback): static
    {
        $this->updatedCallback = $callback === null ? null : new BulkCallback($callback);

        return $this;
    }

    /**
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @return string[]
     */
    protected function getSelectColumns(
        array $uniqueAttributes,
        ?array $updateAttributes,
    ): array {
        if (in_array('*', $this->selectColumns, true)) {
            return ['*'];
        }

        // the case then we have select(<not all>) and we need to update all attributes
        // looks really strange. The additional fields would mark like a change
        if (empty($updateAttributes)) {
            return ['*'];
        }

        return array_unique(
            array_merge(
                $this->selectColumns,
                $uniqueAttributes,
                $updateAttributes,
            )
        );
    }
}
