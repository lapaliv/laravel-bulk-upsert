<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\PostCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\Post;

/**
 * @internal
 *
 * @method Post|PostCollection create($attributes = [], ?Model $parent = null)
 * @method Post|PostCollection make($attributes = [], ?Model $parent = null)
 * @method Post|PostCollection createMany(iterable $records)
 * @method self|static count(?int $count)
 */
abstract class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'text' => $this->faker->text(),
        ];
    }
}
