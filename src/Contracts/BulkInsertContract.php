<?php

/** @noinspection PhpDocSignatureInspection */

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface BulkInsertContract extends BulkSave
{
    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null|void $callback
     * @return $this
     */
    public function onCreating(?callable $callback): static;

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null|void $callback
     * @return $this
     */
    public function onCreated(?callable $callback): static;

    /**
     * @param callable(Collection<BulkModel>): Collection<BulkModel>|null|void $callback
     * @return $this
     */
    public function onSaved(?callable $callback): static;

    /**
     * @param class-string<BulkModel>|BulkModel $model
     * @param string[] $uniqueAttributes
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @param bool $ignore
     * @return void
     */
    public function insert(string|BulkModel $model, array $uniqueAttributes, iterable $rows, bool $ignore): void;

    /**
     * @param class-string<BulkModel>|BulkModel $model
     * @param string[] $uniqueAttributes
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @return void
     */
    public function insertOrIgnore(string|BulkModel $model, array $uniqueAttributes, iterable $rows): void;
}
