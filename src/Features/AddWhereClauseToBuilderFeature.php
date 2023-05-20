<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Contracts\BulkBuilderWhereClause;
use Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported;

/**
 * @internal
 */
class AddWhereClauseToBuilderFeature
{
    public function __construct(
        private GetValueHashFeature $getValueHashFeature
    ) {
        //
    }

    /**
     * @param BulkBuilderWhereClause|EloquentBuilder|QueryBuilder $builder
     * @param string[] $uniqueAttributes
     * @param array<int, array<string, Model|scalar>> $rows
     *
     * @return void
     */
    public function handle(
        QueryBuilder|EloquentBuilder|BulkBuilderWhereClause $builder,
        array $uniqueAttributes,
        iterable $rows
    ): void {
        $uniqueAttributes = $this->getOrderedUniqueAttributes($rows, $uniqueAttributes);
        $this->makeBuilder(
            $builder,
            $rows,
            $uniqueAttributes,
            0
        );
    }

    /**
     * @param BulkBuilderWhereClause|EloquentBuilder|QueryBuilder $builder
     * @param iterable $rows
     * @param string[] $uniqueAttributes
     * @param int $uniqAttributeIndex
     *
     * @return void
     */
    protected function makeBuilder(
        QueryBuilder|EloquentBuilder|BulkBuilderWhereClause $builder,
        iterable $rows,
        array $uniqueAttributes,
        int $uniqAttributeIndex
    ): void {
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

    private function addCondition(
        QueryBuilder|EloquentBuilder|BulkBuilderWhereClause $builder,
        string $column,
        mixed $value
    ): void {
        if (is_scalar($value) || $value === null) {
            $builder->where($column, '=', $value);
        } elseif (is_object($value) && PHP_VERSION_ID >= 80100 && enum_exists(get_class($value))) {
            $builder->where($column, $value->value);
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            $builder->where($column, '=', $value->__toString());
        } elseif (is_array($value) && count($value) === 1) {
            $builder->where($column, '=', $value[0]);
        } elseif (is_array($value)) {
            $builder->whereIn($column, $value);
        } else {
            throw new BulkValueTypeIsNotSupported($value);
        }
    }

    /**
     * @param array<int, array<string, scalar>> $rows
     * @param string $column
     *
     * @return array<string, array{original: mixed, children: mixed[]}>
     */
    private function groupBy(iterable $rows, string $column): array
    {
        $result = [];

        foreach ($rows as $row) {
            $value = $this->getValue($row, $column);

            $valueHash = $this->getValueHashFeature->handle($value);

            $result[$valueHash] ??= ['original' => $value, 'children' => []];
            $result[$valueHash]['children'][] = $row;
        }

        return $result;
    }

    /**
     * Returns values from groups with original type.
     *
     * @param mixed[] $groups
     *
     * @return scalar[]
     */
    private function getOriginalsFromGroup(array $groups): array
    {
        $result = [];

        foreach ($groups as $group) {
            $result[] = $group['original'];
        }

        return $result;
    }

    private function getOrderedUniqueAttributes(iterable $rows, array $uniqueAttributes): array
    {
        if (count($uniqueAttributes) === 1) {
            return $uniqueAttributes;
        }

        $groups = [];

        foreach ($rows as $row) {
            foreach ($uniqueAttributes as $uniqueAttribute) {
                $groups[$uniqueAttribute] ??= [];

                $value = $this->getValue($row, $uniqueAttribute);
                $valueHash = $this->getValueHashFeature->handle($value);
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

    private function getValue(array|Model $row, string $column): mixed
    {
        if ($row instanceof Model) {
            return $row->getAttribute($column);
        }

        return $row[$column] ?? null;
    }
}
