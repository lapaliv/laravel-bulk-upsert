<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class BulkAddWhereClauseToBuilderFeature
{
    /**
     * @param string[] $uniqueAttributes
     */
    public function __construct(private array $uniqueAttributes)
    {
        //
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $builder
     * @param array<int, array<string, scalar>> $rows
     * @return void
     */
    public function handle(EloquentBuilder|QueryBuilder $builder, array $rows): void
    {
        $this->makeBuilder(
            $builder,
            $this->groupBy($rows, $this->uniqueAttributes[0]),
            0
        );
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $builder
     * @param array<scalar, array<int, array<string, mixed>>> $groups
     * @param int $uniqAttributeIndex
     * @return void
     */
    protected function makeBuilder(EloquentBuilder|QueryBuilder $builder, array $groups, int $uniqAttributeIndex): void
    {
        $column = $this->uniqueAttributes[$uniqAttributeIndex];

        if (array_key_exists($uniqAttributeIndex + 1, $this->uniqueAttributes)) {
            foreach ($groups as $value => $children) {
                $builder->orWhere(
                    function (EloquentBuilder $builder) use ($column, $value, $children, $uniqAttributeIndex) {
                        $builder
                            ->where($column, $value)
                            ->where(
                                function (EloquentBuilder $builder) use ($children, $uniqAttributeIndex) {
                                    $this->makeBuilder($builder, $children, $uniqAttributeIndex);
                                }
                            );
                    }
                );
            }
        } elseif (count($groups) === 1) {
            $builder->where($column, array_keys($groups)[0]);
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