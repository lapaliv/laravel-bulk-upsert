<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface BulkUpsertContract extends BulkSave
{
    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null $callback
     * @return $this
     */
    public function onCreating(?callable $callback): static;

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null $callback
     * @return $this
     */
    public function onCreated(?callable $callback): static;

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null $callback
     * @return $this
     */
    public function onUpdating(?callable $callback): static;

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null $callback
     * @return $this
     */
    public function onUpdated(?callable $callback): static;

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null $callback
     * @return $this
     */
    public function onSaving(?callable $callback): static;

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null $callback
     * @return $this
     */
    public function onSaved(?callable $callback): static;

    /**
     * @param string|BulkModel $model
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @param string[] $uniqueAttributes
     * @param array|null $updateAttributes
     * @return void
     */
    public function upsert(
        string|BulkModel $model,
        iterable $rows,
        array $uniqueAttributes,
        ?array $updateAttributes = null,
    ): void;
}
