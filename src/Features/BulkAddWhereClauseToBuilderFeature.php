<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\BulkSqlBuilderWhereClause;

class BulkAddWhereClauseToBuilderFeature
{
    /**
     * @param EloquentBuilder|QueryBuilder|BulkSqlBuilderWhereClause $builder
     * @param string[] $uniqueAttributes
     * @param array<int, array<string, scalar|BulkModel>> $rows
     * @return void
     */
    public function handle(
        EloquentBuilder|QueryBuilder|BulkSqlBuilderWhereClause $builder,
        array $uniqueAttributes,
        iterable $rows
    ): void
    {
        $this->makeBuilder(
            $builder,
            $this->groupBy($rows, $uniqueAttributes[0]),
            $uniqueAttributes,
            0
        );
    }

    /**
     * @param EloquentBuilder|QueryBuilder|BulkSqlBuilderWhereClause $builder
     * @param array<scalar, array<int, array<string, mixed>>> $groups
     * @param string[] $uniqueAttributes
     * @param int $uniqAttributeIndex
     * @return void
     */
    protected function makeBuilder(
        EloquentBuilder|QueryBuilder|BulkSqlBuilderWhereClause $builder,
        array $groups,
        array $uniqueAttributes,
        int $uniqAttributeIndex
    ): void
    {
        $column = $uniqueAttributes[$uniqAttributeIndex];

        if (array_key_exists($uniqAttributeIndex + 1, $uniqueAttributes)) {
            foreach ($groups as $value => $children) {
                $builder->orWhere(
                    function (EloquentBuilder|QueryBuilder|BulkSqlBuilderWhereClause $builder) use ($column, $value, $children, $uniqueAttributes, $uniqAttributeIndex): void {
                        $builder
                            ->where($column, '=', $value)
                            ->where(
                                function (EloquentBuilder|QueryBuilder|BulkSqlBuilderWhereClause $builder) use ($children, $uniqueAttributes, $uniqAttributeIndex): void {
                                    $this->makeBuilder($builder, $children, $uniqueAttributes, $uniqAttributeIndex);
                                }
                            );
                    }
                );
            }
        } elseif (count($groups) === 1) {
            $builder->where($column, '=', array_keys($groups)[0]);
        } else {
            $builder->whereIn($column, array_keys($groups));
        }
    }

    /**
     * @param array<int, array<string, scalar>> $rows
     * @param string $column
     * @return array<scalar, array<int, array<string, mixed>>>
     */
    protected function groupBy(array $rows, string $column): array
    {
        $result = [];

        foreach ($rows as $row) {
            $value = $row[$column] ?? null;
            $result[$value] ??= [];
            $result[$value][] = $row;
        }

        return $result;
    }
}
