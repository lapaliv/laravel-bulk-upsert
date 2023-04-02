<?php

namespace Lapaliv\BulkUpsert\Collection;

use Countable;
use Iterator;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Entities\BulkRow;

/**
 * @template TModel of BulkModel
 * @template TOriginal of mixed
 *
 * @implements Iterator<int, BulkRow<TModel, TOriginal>>
 */
class BulkRows implements Iterator, Countable
{
    private int $index = 0;

    /**
     * @param BulkRow<TModel, TOriginal>[] $items
     */
    public function __construct(private array $items = [])
    {
        //
    }

    /**
     * Return the current element.
     *
     * @see https://php.net/manual/en/iterator.current.php
     *
     * @return BulkRow<TModel, TOriginal> can return any type
     */
    public function current(): mixed
    {
        return $this->items[$this->index];
    }

    /**
     * Move forward to next element.
     *
     * @see https://php.net/manual/en/iterator.next.php
     *
     * @return void any returned value is ignored
     */
    public function next(): void
    {
        ++$this->index;
    }

    /**
     * Return the key of the current element.
     *
     * @see https://php.net/manual/en/iterator.key.php
     *
     * @return int|null TKey on success, or null on failure
     */
    public function key(): mixed
    {
        return $this->index;
    }

    /**
     * Checks if current position is valid.
     *
     * @see https://php.net/manual/en/iterator.valid.php
     *
     * @return bool The return value will be casted to boolean and then evaluated.
     *              Returns true on success or false on failure.
     */
    public function valid(): bool
    {
        return array_key_exists($this->index, $this->items);
    }

    /**
     * Rewind the Iterator to the first element.
     *
     * @see https://php.net/manual/en/iterator.rewind.php
     *
     * @return void any returned value is ignored
     */
    public function rewind(): void
    {
        $this->index = 0;
    }

    /**
     * @param BulkRow<TModel, TOriginal> $row
     *
     * @return $this
     */
    public function push(BulkRow $row): static
    {
        $this->items[] = $row;

        return $this;
    }

    /**
     * Count elements of an object
     * @link https://php.net/manual/en/countable.count.php
     * @return int<0,max> The custom count as an integer.
     * <p>
     * The return value is cast to an integer.
     * </p>
     */
    public function count(): int
    {
        return count($this->items);
    }
}
