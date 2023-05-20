<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\StoryCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlStory;

/**
 * @internal
 *
 * @method MySqlStory|StoryCollection create($attributes = [], ?Model $parent = null)
 * @method MySqlStory|StoryCollection make($attributes = [], ?Model $parent = null)
 * @method MySqlStory|StoryCollection createMany(iterable $records)
 */
final class MySqlStoryFactory extends StoryFactory
{
    protected $model = MySqlStory::class;
}
