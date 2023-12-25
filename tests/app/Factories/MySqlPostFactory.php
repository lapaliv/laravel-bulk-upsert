<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\PostCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlPost;

/**
 * @internal
 *
 * @method MySqlPost|PostCollection create($attributes = [], ?Model $parent = null)
 * @method MySqlPost|PostCollection make($attributes = [], ?Model $parent = null)
 * @method MySqlPost|PostCollection createMany(iterable $records)
 */
final class MySqlPostFactory extends PostFactory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<MySqlPost>
     */
    protected $model = MySqlPost::class;
}
