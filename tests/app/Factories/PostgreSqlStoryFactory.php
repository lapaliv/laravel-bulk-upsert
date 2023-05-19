<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\StoryCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlStory;

/**
 * @internal
 *
 * @method PostgreSqlStory|StoryCollection create($attributes = [], ?Model $parent = null)
 * @method PostgreSqlStory|StoryCollection make($attributes = [], ?Model $parent = null)
 * @method PostgreSqlStory|StoryCollection createMany(iterable $records)
 */
final class PostgreSqlStoryFactory extends StoryFactory
{
    protected $model = PostgreSqlStory::class;
}
