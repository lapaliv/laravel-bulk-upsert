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
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<MySqlStory>
     */
    protected $model = MySqlStory::class;
}
