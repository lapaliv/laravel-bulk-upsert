<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\StoryCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteStory;

/**
 * @internal
 *
 * @method SqLiteStory|StoryCollection create($attributes = [], ?Model $parent = null)
 * @method SqLiteStory|StoryCollection make($attributes = [], ?Model $parent = null)
 * @method SqLiteStory|StoryCollection createMany(iterable $records)
 */
final class SqLiteStoryFactory extends StoryFactory
{
    protected $model = SqLiteStory::class;
}
