<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface BulkUpdateContract extends BulkSave
{
    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null|null $callback
     * @return $this
     */
    public function onUpdating(?callable $callback): static;

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null|void $callback
     * @return $this
     */
    public function onUpdated(?callable $callback): static;

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null|void $callback
     * @return $this
     */
    public function onSaving(?callable $callback): static;

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null|void $callback
     * @return $this
     */
    public function onSaved(?callable $callback): static;

    /**
     * @param class-string<BulkModel>|BulkModel $model
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @param string[] $uniqueAttributes
     * @param array|null $updateAttributes
     * @return void
     */
    public function update(
        string|BulkModel $model,
        iterable $rows,
        ?array $uniqueAttributes = null,
        ?array $updateAttributes = null,
    ): void;
}
