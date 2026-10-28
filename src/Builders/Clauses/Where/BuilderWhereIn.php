<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses\Where;

/**
 * An IN condition of a WHERE clause, like `column in (values)`.
 *
 * @internal
 *
 * @psalm-api
 */
readonly class BuilderWhereIn implements BuilderWhere
{
    /**
     * Create a new IN condition.
     *
     * @param string $field
     * @param array<scalar|null> $values
     * @param string $boolean
     */
    public function __construct(
        private string $field,
        private array $values,
        private string $boolean
    )
    {
        //
    }

    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @return array<scalar|null>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function getBoolean(): string
    {
        return $this->boolean;
    }
}
