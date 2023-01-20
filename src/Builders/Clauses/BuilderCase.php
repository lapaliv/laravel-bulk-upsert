<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses;

use Lapaliv\BulkUpsert\Builders\Clauses\Case\BuilderCaseWhen;

class BuilderCase
{
    /** @var BuilderCaseWhen[] */
    private array $whens = [];
    private BuilderRawExpression|int|float|string|bool|null $else = null;

    /**
     * @return BuilderCaseWhen[]
     */
    public function getWhens(): array
    {
        return $this->whens;
    }

    public function addWhenThen(BuilderCaseWhen $builderCaseWhen): static
    {
        $this->whens[] = $builderCaseWhen;

        return $this;
    }

    public function else(BuilderRawExpression|int|float|string|bool|null $value): static
    {
        $this->else = $value;

        return $this;
    }

    public function getElse(): BuilderRawExpression|int|float|string|bool|null
    {
        return $this->else;
    }
}
