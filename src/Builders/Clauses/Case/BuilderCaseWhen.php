<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses\Case;

use Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderWhereClause;

/**
 * One WHEN ... THEN branch of a CASE expression.
 *
 * The condition of the branch is collected by the WHERE clause of the builder.
 *
 * @internal
 */
class BuilderCaseWhen
{
    use BuilderWhereClause;

    /**
     * The value of the THEN part of the branch.
     *
     * @var \Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression|bool|float|int|string|null
     */
    public BuilderRawExpression|int|float|string|bool|null $then = null;

    /**
     * Get the value of the THEN part of the branch.
     *
     * @return \Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression|bool|float|int|string|null
     */
    public function getThen(): BuilderRawExpression|int|float|string|bool|null
    {
        return $this->then;
    }

    /**
     * Set the value of the THEN part of the branch.
     *
     * @param \Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression|bool|float|int|string|null $value
     *
     * @return $this
     */
    public function setThen(BuilderRawExpression|int|float|string|bool|null $value): static
    {
        $this->then = $value;

        return $this;
    }
}
