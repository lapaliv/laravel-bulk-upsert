<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Lapaliv\BulkUpsert\Contracts\BuilderWhereClause;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

class AddWhereClauseToBuilderFeature
{
    /**
     * @param QueryBuilder|BuilderWhereClause $builder
     * @param string[] $uniqueAttributes
     * @param array<int, array<string, scalar|BulkModel>> $rows
     * @return void
     */
    public function handle(
        QueryBuilder|BuilderWhereClause $builder,
        array $uniqueAttributes,
        iterable $rows
    ): void {
        $this->makeBuilder(
            $builder,
            $rows,
            $uniqueAttributes,
            0
        );
    }

    /**
     * @param QueryBuilder|BuilderWhereClause $builder
     * @param array<scalar, array<int, array<string, mixed>>> $groups
     * @param string[] $uniqueAttributes
     * @param int $uniqAttributeIndex
     * @return void
     */
    protected function makeBuilder(
        QueryBuilder|BuilderWhereClause $builder,
        iterable $rows,
        array $uniqueAttributes,
        int $uniqAttributeIndex
    ): void {
        $column = $uniqueAttributes[$uniqAttributeIndex];
        $groups = $this->groupBy($rows, $column);

        if (array_key_exists($uniqAttributeIndex + 1, $uniqueAttributes)) {
            foreach ($groups as $value => $children) {
                $builder->orWhere(
                    function (QueryBuilder|BuilderWhereClause $builder) use ($column, $value, $children, $uniqueAttributes, $uniqAttributeIndex): void {
                        $this->addCondition($builder, $column, $value);

                        // the latest child
                        if (array_key_exists($uniqAttributeIndex + 2, $uniqueAttributes) === false) {
                            $childrenGroups = $this->groupBy($children, $uniqueAttributes[$uniqAttributeIndex + 1]);
                            $this->addCondition(
                                $builder,
                                $uniqueAttributes[$uniqAttributeIndex + 1],
                                array_values($childrenGroups)
                            );
                        } else {
                            $builder->where(
                                function (QueryBuilder|BuilderWhereClause $builder) use ($children, $uniqueAttributes, $uniqAttributeIndex): void {
                                    $this->makeBuilder(
                                        $builder,
                                        $children,
                                        $uniqueAttributes,
                                        $uniqAttributeIndex + 1,
                                    );
                                }
                            );
                        }
                    }
                );
            }
        } else {
            $this->addCondition($builder, $column, array_keys($groups));
        }
    }

    private function addCondition(QueryBuilder|BuilderWhereClause $builder, string $column, mixed $value): void
    {
        if (is_scalar($value)) {
            $builder->where($column, '=', $value);
        } elseif (count($value) === 1) {
            $builder->where($column, '=', $value[0]);
        } else {
            $builder->whereIn($column, $value);
        }
    }

    /**
     * @param array<int, array<string, scalar>> $rows
     * @param string $column
     * @return array<scalar, array<int, array<string, mixed>>>
     */
    private function groupBy(iterable $rows, string $column): array
    {
        $result = [];

        foreach ($rows as $row) {
            if ($row instanceof BulkModel) {
                $value = $row->getAttribute($column);
            } else {
                $value = $row[$column] ?? null;
            }

            $result[$value] ??= [];
            $result[$value][] = $row;
        }

        return $result;
    }
}