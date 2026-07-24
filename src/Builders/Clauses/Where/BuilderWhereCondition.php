<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses\Where;

/**
 * A single condition of a WHERE clause, like `column = value`.
 *
 * @internal
 *
 * @psalm-api
 */
readonly class BuilderWhereCondition implements BuilderWhere
{
    /**
     * Create a new condition.
     *
     * @param string $field The name of the column.
     * @param string $operator The comparison operator.
     * @param mixed $value The value which the column is compared with.
     * @param string $boolean The keyword which joins this condition with the previous one.
     */
    public function __construct(
        private string $field,
        private string $operator,
        private mixed $value,
        private string $boolean
    )
    {
        //
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getBoolean(): string
    {
        return $this->boolean;
    }
}
