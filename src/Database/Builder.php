<?php

namespace Lapaliv\BulkUpsert\Database;

use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Database\Grammar\Grammar;

class Builder
{
    public function insert(Grammar $grammar): InsertBuilder
    {
        return new InsertBuilder($grammar);
    }
}
