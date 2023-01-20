<?php

namespace Lapaliv\BulkUpsert\Builders\Clauses\Case;

use Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderWhere;

class BuilderCaseWhen
{
    use BuilderWhere;

    public BuilderRawExpression|int|float|string|bool|null $then = null;

    public function getThen(): BuilderRawExpression|int|float|string|bool|null
    {
        return $this->then;
    }

    public function then(BuilderRawExpression|int|float|string|bool|null $value): static
    {
        $this->then = $value;

        return $this;
    }
}
