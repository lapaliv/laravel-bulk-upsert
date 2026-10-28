<?php

namespace Lapaliv\BulkUpsert\Builders;

use BackedEnum;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Builders\Clauses\BulkBuilderWhereClause;
use Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported;
use Lapaliv\BulkUpsert\Support\ValueHasher;

/**
 * Adds a WHERE clause which matches the given rows by their unique attributes
 * to the provided builder.
 *
 * @internal
 */
readonly class WhereClauseApplier
{
    /**
     * Create a new applier instance.
     *
     * @param \Lapaliv\BulkUpsert\Support\ValueHasher $valueHasher
     */
    public function __construct(
        private ValueHasher $valueHasher
    )
    {
        //
    }

    /**
     * Add the conditions which match the given rows to the builder.
     *
     * The unique attributes are reordered first, so that the attribute with the
     * smallest number of the different values becomes the outer condition.
     *
     * @param \Lapaliv\BulkUpsert\Builders\Clauses\BulkBuilderWhereClause|\Illuminate\Database\Eloquent\Builder|\Illuminate\Contracts\Database\Query\Builder $builder
     * @param string[] $uniqueAttributes
     * @param iterable<int, \Illuminate\Database\Eloquent\Model|array<string, scalar>> $rows
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported
     */
    public function handle(
        QueryBuilder|EloquentBuilder|BulkBuilderWhereClause $builder,
        array $uniqueAttributes,
        iterable $rows
    ): void
    {
        $uniqueAttributes = $this->getOrderedUniqueAttributes($rows, $uniqueAttributes);
        $this->makeBuilder(
            $builder,
            $rows,
            $uniqueAttributes,
            0
        );
    }

    /**
     * Add the conditions for one level of the unique attributes.
     *
     * The rows are grouped by the current attribute. When it is the last attribute,
     * a single condition with all its values is added. Otherwise each group gets its
     * own OR branch, and the method calls itself for the next attribute.
     *
     * @param \Lapaliv\BulkUpsert\Builders\Clauses\BulkBuilderWhereClause|\Illuminate\Database\Eloquent\Builder|\Illuminate\Contracts\Database\Query\Builder $builder
     * @param iterable<int, \Illuminate\Database\Eloquent\Model|array<string, scalar>> $rows
     * @param string[] $uniqueAttributes
     * @param int $uniqAttributeIndex The index of the attribute which is handled now.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported
     */
    protected function makeBuilder(
        QueryBuilder|EloquentBuilder|BulkBuilderWhereClause $builder,
        iterable $rows,
        array $uniqueAttributes,
        int $uniqAttributeIndex
    ): void
    {
        $column = $uniqueAttributes[$uniqAttributeIndex];
        $groups = $this->groupBy($rows, $column);

        if (array_key_exists($uniqAttributeIndex + 1, $uniqueAttributes)) {
            foreach ($groups as $children) {
                $builder->orWhere(
                    function (QueryBuilder|EloquentBuilder|BulkBuilderWhereClause $builder) use ($column, $children, $uniqueAttributes, $uniqAttributeIndex): void {
                        $this->addCondition($builder, $column, $children['original']);

                        // the latest child
                        if (!array_key_exists($uniqAttributeIndex + 2, $uniqueAttributes)) {
                            $childrenGroups = $this->groupBy($children['children'], $uniqueAttributes[$uniqAttributeIndex + 1]);

                            $this->addCondition(
                                $builder,
                                $uniqueAttributes[$uniqAttributeIndex + 1],
                                $this->getOriginalsFromGroup($childrenGroups)
                            );
                        } else {
                            $builder->where(
                                function (QueryBuilder|EloquentBuilder|BulkBuilderWhereClause $builder) use ($children, $uniqueAttributes, $uniqAttributeIndex): void {
                                    $this->makeBuilder(
                                        $builder,
                                        $children['children'],
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
            $this->addCondition($builder, $column, $this->getOriginalsFromGroup($groups));
        }
    }

    /**
     * Add one condition for the given column and value to the builder.
     *
     * Scalars, null, backed enums and objects with the `__toString()` method are
     * compared by the equality operator. An array with one item is compared in the
     * same way, and a longer array becomes an IN condition.
     *
     * @param \Lapaliv\BulkUpsert\Builders\Clauses\BulkBuilderWhereClause|\Illuminate\Database\Eloquent\Builder|\Illuminate\Contracts\Database\Query\Builder $builder
     * @param string $column
     * @param mixed $value
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported
     */
    private function addCondition(
        QueryBuilder|EloquentBuilder|BulkBuilderWhereClause $builder,
        string $column,
        mixed $value
    ): void
    {
        if (is_scalar($value) || $value === null) {
            $builder->where($column, '=', $value);
        } elseif ($value instanceof BackedEnum) {
            $builder->where($column, '=', $value->value);
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            $builder->where($column, '=', $value->__toString());
        } elseif (is_array($value) && count($value) === 1) {
            $builder->where($column, '=', $value[0]);
        } elseif (is_array($value)) {
            // `IN (...)` never matches NULL, so a NULL value gets its own `IS NULL` branch,
            // grouped with the IN list, so the outer AND/OR of the caller stays as it is.
            $values = array_values(array_filter($value, static fn (mixed $item): bool => $item !== null));

            if (count($values) === count($value)) {
                $builder->whereIn($column, $values);
            } elseif ($values === []) {
                $builder->where($column, '=', null);
            } else {
                $builder->where(
                    function (QueryBuilder|EloquentBuilder|BulkBuilderWhereClause $builder) use ($column, $values): void {
                        $this->addCondition($builder, $column, $values);
                        $builder->orWhere($column, '=', null);
                    }
                );
            }
        } else {
            throw new BulkValueTypeIsNotSupported($value);
        }
    }

    /**
     * Group the rows by the value of the given column.
     *
     * Each group keeps the value with its original type and the rows which have it.
     *
     * @param iterable<int, \Illuminate\Database\Eloquent\Model|array<string, scalar>> $rows
     * @param string $column
     *
     * @return array<string, array{original: mixed, children: list<\Illuminate\Database\Eloquent\Model|array<string, scalar>>}>
     */
    private function groupBy(iterable $rows, string $column): array
    {
        $result = [];

        foreach ($rows as $row) {
            /** @psalm-suppress MixedAssignment */
            $value = $this->getValue($row, $column);

            $valueHash = $this->valueHasher->handle($value);

            $result[$valueHash] ??= ['original' => $value, 'children' => []];
            $result[$valueHash]['children'][] = $row;
        }

        return $result;
    }

    /**
     * Get the value of each group with its original type.
     *
     * @param array<string, array{original: mixed, children: mixed}> $groups
     *
     * @return list<mixed>
     */
    private function getOriginalsFromGroup(array $groups): array
    {
        $result = [];

        foreach ($groups as $group) {
            /** @psalm-suppress MixedAssignment */
            $result[] = $group['original'];
        }

        return $result;
    }

    /**
     * Sort the unique attributes by the number of their different values.
     *
     * The attribute with the smallest number of the different values goes first,
     * because it produces the smallest number of the OR branches. The given order is
     * kept when there is only one attribute or when there are no rows at all.
     *
     * @param iterable<int, \Illuminate\Database\Eloquent\Model|array<string, scalar>> $rows
     * @param string[] $uniqueAttributes
     *
     * @return string[]
     */
    private function getOrderedUniqueAttributes(iterable $rows, array $uniqueAttributes): array
    {
        if (count($uniqueAttributes) === 1) {
            return $uniqueAttributes;
        }

        $groups = [];

        foreach ($rows as $row) {
            foreach ($uniqueAttributes as $uniqueAttribute) {
                $groups[$uniqueAttribute] ??= [];

                /** @psalm-suppress MixedAssignment */
                $value = $this->getValue($row, $uniqueAttribute);
                $valueHash = $this->valueHasher->handle($value);
                $groups[$uniqueAttribute][$valueHash] ??= $valueHash;
            }
        }

        if (empty($groups)) {
            return $uniqueAttributes;
        }

        $result = [];

        foreach ($groups as $uniqueAttribute => $values) {
            $result[$uniqueAttribute] = count($values);
        }

        asort($result, SORT_NUMERIC);

        return array_keys($result);
    }

    /**
     * Get the value of the given column of the row.
     *
     * @param array<string, scalar>|\Illuminate\Database\Eloquent\Model $row
     * @param string $column
     *
     * @return mixed Null when the array does not have such a key.
     */
    private function getValue(array|Model $row, string $column): mixed
    {
        if ($row instanceof Model) {
            return $row->getAttribute($column);
        }

        return $row[$column] ?? null;
    }
}
