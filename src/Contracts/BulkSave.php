<?php

namespace Lapaliv\BulkUpsert\Contracts;

interface BulkSave
{
    /**
     * @param int $size
     * @param callable(Collection<BulkModel> $chunk): Collection<BulkModel>|null $callback
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
     * @param string[] $columns
     * @return $this
     */
    public function select(array $columns = ['*']): static;
}
