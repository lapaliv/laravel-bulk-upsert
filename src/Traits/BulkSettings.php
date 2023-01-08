<?php

namespace Lapaliv\BulkUpsert\Traits;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

trait BulkSettings
{
    private int $chunkSize = 100;
    private ?Closure $chunkCallback = null;
    private array $selectColumns = ['*'];

    /**
     * @param int $size
     * @param (callable(BulkModel[] $chunk): BulkModel[])|null $callback
     * @return $this
     */
    public function chunk(int $size = 100, ?callable $callback = null): static
    {
        $this->chunkSize = $size;

        if ($callback !== null) {
            $this->chunkCallback = is_callable($callback)
                ? Closure::fromCallable($callback)
                : $callback;
        }

        return $this;
    }

    /**
     * @param string[] $events
     * @return $this
     */
    public function events(array $events): static
    {
        $this->events = $events;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param string[] $columns
     * @return $this
     */
    public function select(array $columns = ['*']): static
    {
        $this->selectColumns = in_array('*', $columns, true)
            ? ['*']
            : $columns;

        return $this;
    }

    /**
     * @param BulkModel $model
     * @param array<int, BulkModel|mixed[]>|Collection<int, BulkModel>|iterable $rows
     * @param Closure $callback
     * @return void
     */
    protected function separate(
        BulkModel $model,
        iterable $rows,
        Closure $callback,
    ): void
    {
        $chunk = [];

        foreach ($rows as $key => $row) {
            if ($row instanceof BulkModel) {
                $chunk[$key] = $row;
            } else {
                $chunk[$key] = new $model();
                $chunk[$key]->fill((array)$row);
            }

            if ($this->chunkSize > 0 && count($chunk) % $this->chunkSize === 0) {
                $callback($chunk);
                $chunk = [];
            }
        }

        if (empty($chunk) === false) {
            $callback($chunk);
        }
    }
}
