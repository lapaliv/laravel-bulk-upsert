<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses;

/**
 * Wraps a piece of SQL which has to be put into the statement as is.
 *
 * @internal
 */
readonly class BuilderRawExpression
{
    /**
     * Create a new raw expression.
     *
     * @param string $expression The SQL text of the expression.
     */
    public function __construct(private string $expression)
    {
        // Nothing
    }

    /**
     * Get the SQL text of the expression.
     *
     * @return string
     */
    public function get(): string
    {
        return $this->expression;
    }
}
