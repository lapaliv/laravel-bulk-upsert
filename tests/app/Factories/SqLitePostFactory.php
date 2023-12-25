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
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SqLitePost>
     */
    protected $model = SqLitePost::class;
}
