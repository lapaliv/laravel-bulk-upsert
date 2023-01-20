<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

class MySqlArticle extends Article
{
    protected $connection = 'mysql';
}
