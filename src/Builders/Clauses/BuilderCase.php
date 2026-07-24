<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses;

use Lapaliv\BulkUpsert\Builders\Clauses\Case\BuilderCaseWhen;

/**
 * Holds the WHEN branches and the ELSE value of a SQL CASE expression.
 *
 * @internal
 */
class BuilderCase
{
    /**
     * The WHEN ... THEN branches of the expression.
     *
     * @var \Lapaliv\BulkUpsert\Builders\Clauses\Case\BuilderCaseWhen[]
     */
    private array $whens = [];

    /**
     * The value of the ELSE branch.
     *
     * @var \Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression|bool|float|int|string|null
     */
    private BuilderRawExpression|int|float|string|bool|null $else = null;

    /**
     * Get all the WHEN branches of the expression.
     *
     * @return \Lapaliv\BulkUpsert\Builders\Clauses\Case\BuilderCaseWhen[]
     */
    public function getWhens(): array
    {
        return $this->whens;
    }

    /**
     * Add one more WHEN ... THEN branch to the expression.
     *
     * @param \Lapaliv\BulkUpsert\Builders\Clauses\Case\BuilderCaseWhen $builderCaseWhen
     *
     * @return $this
     */
    public function addWhenThen(BuilderCaseWhen $builderCaseWhen): static
    {
        $this->whens[] = $builderCaseWhen;

        return $this;
    }

    /**
     * Set the value of the ELSE branch.
     *
     * @param \Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression|bool|float|int|string|null $value
     *
     * @return $this
     */
    public function setElse(BuilderRawExpression|int|float|string|bool|null $value): static
    {
        $this->else = $value;

        return $this;
    }

    /**
     * Get the value of the ELSE branch.
     *
     * @return \Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression|bool|float|int|string|null
     */
    public function getElse(): BuilderRawExpression|int|float|string|bool|null
    {
        return $this->else;
    }
}
