<?php

namespace Lapaliv\BulkUpsert\Traits;

trait BulkSelectTrait
{
    /**
     * @var string[]
     */
    private array $selectColumns = ['*'];

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
}
