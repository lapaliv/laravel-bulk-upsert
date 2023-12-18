<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\PostCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLitePost;

/**
 * @internal
 *
 * @method PostCollection|SqLitePost create($attributes = [], ?Model $parent = null)
 * @method PostCollection|SqLitePost make($attributes = [], ?Model $parent = null)
 * @method PostCollection|SqLitePost createMany(iterable $records)
 */
final class SqLitePostFactory extends PostFactory
{
    protected $model = SqLitePost::class;
}
