<?php

namespace Lapaliv\BulkUpsert\Tests\Models;

class MysqlArticle extends Article
{
    protected $connection = 'mysql';
}