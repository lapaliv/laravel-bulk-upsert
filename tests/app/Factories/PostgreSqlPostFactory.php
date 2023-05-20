<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\PostCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlPost;

/**
 * @internal
 *
 * @method PostCollection|PostgreSqlPost create($attributes = [], ?Model $parent = null)
 * @method PostCollection|PostgreSqlPost make($attributes = [], ?Model $parent = null)
 * @method PostCollection|PostgreSqlPost createMany(iterable $records)
 */
final class PostgreSqlPostFactory extends PostFactory
{
    protected $model = PostgreSqlPost::class;
}
