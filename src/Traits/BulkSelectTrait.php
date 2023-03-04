<?php

namespace Lapaliv\BulkUpsert\Traits;

use Lapaliv\BulkUpsert\Contracts\BulkModel;

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

    /**
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @return string[]
     */
    protected function getSelectColumns(
        BulkModel $model,
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
