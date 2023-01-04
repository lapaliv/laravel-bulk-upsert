<?php

namespace Lapaliv\BulkUpsert\Tests\Models;

class PostgresArticle extends Article
{
    protected $connection = 'postgres';
}