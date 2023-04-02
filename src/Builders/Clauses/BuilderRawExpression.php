<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses;

/**
 * @internal
 */
class BuilderRawExpression
{
    public function __construct(private string $expression)
    {
        // Nothing
    }

    public function get(): string
    {
        return $this->expression;
    }
}
