<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface BulkUpdateContract extends BulkSave
{
    /**
     * @param ?callable(Collection<BulkModel>): Collection<BulkModel>|null|void $callback
     * @return $this
     */
    public function onUpdating(?callable $callback): static;

    /**
     * @param ?callable(Collection<BulkModel>): Collection<BulkModel>|null|void $callback
     * @return $this
     */
    public function onUpdated(?callable $callback): static;

    /**
     * @param ?callable(Collection<BulkModel>): Collection<BulkModel>|null|void $callback
     * @return $this
     */
    public function onSaving(?callable $callback): static;

    /**
     * @param ?callable(Collection<BulkModel>): Collection<BulkModel>|null|void $callback
     * @return $this
     */
    public function onSaved(?callable $callback): static;

    /**
     * @param string[] $events
     * @return $this
     */
    public function setEvents(array $events): static;

    public function disableEvents(): static;

    /**
     * @param string[] $columns
     * @return $this
     */
    public function select(array $columns = ['*']): static;

    /**
     * @param int $size
     * @param ?callable(Collection<BulkModel>): Collection<BulkModel>|null|void $callback
     * @return $this
     */
    public function chunk(int $size = 100, ?callable $callback = null): static;

    /**
     * @param class-string<BulkModel>|BulkModel $eloquent
     * @param iterable|Collection<BulkModel>|array<scalar, array[]> $rows
     * @param string[] $uniqueAttributes
     * @param array|null $updateAttributes
     * @return void
     */
    public function update(
        string|BulkModel $eloquent,
        iterable $rows,
        ?array $uniqueAttributes = null,
        ?array $updateAttributes = null,
    ): void;
}
