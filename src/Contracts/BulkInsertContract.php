<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface BulkInsertContract
{
    /**
     * @param int $size
     * @param callable(BulkModel[] $chunk): BulkModel[]|null $callback
     * @return $this
     */
    public function chunk(int $size = 100, ?callable $callback = null): static;

    /**
     * @return string[]
     */
    public function getEvents(): array;

    /**
     * @param string[] $events
     * @return $this
     */
    public function setEvents(array $events): static;

    public function disableEvents(): static;

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
    public function onSaved(?callable $callback): static;

    /**
     * @param string[] $columns
     * @return $this
     */
    public function select(array $columns = ['*']): static;

    /**
     * @param string|BulkModel $model
     * @param string[] $uniqueAttributes
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @return void
     */
    public function insert(string|BulkModel $model, array $uniqueAttributes, iterable $rows, bool $ignore): void;

    /**
     * @param string|BulkModel $model
     * @param string[] $uniqueAttributes
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @return void
     */
    public function insertOrIgnore(string|BulkModel $model, array $uniqueAttributes, iterable $rows): void;
}
