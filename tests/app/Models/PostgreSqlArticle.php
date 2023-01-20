<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

class PostgreSqlArticle extends Article
{
    protected $connection = 'postgres';
}
