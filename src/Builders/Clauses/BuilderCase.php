<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses;

class BuilderCase
{
    use BuilderWhere;

    public BuilderRawExpression|int|float|string|bool|null $then = null;
    public BuilderRawExpression|int|float|string|bool|null $else = null;

    public function getThen(): BuilderRawExpression|int|float|string|bool|null
    {
        return $this->then;
    }

    public function then(BuilderRawExpression|int|float|string|bool|null $value): static
    {
        $this->then = $value;

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
