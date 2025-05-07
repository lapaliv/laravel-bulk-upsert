<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\StoryCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\Story;

/**
 * @internal
 *
 * @method Story|StoryCollection create($attributes = [], ?Model $parent = null)
 * @method Story|StoryCollection make($attributes = [], ?Model $parent = null)
 * @method Story|StoryCollection createMany(iterable $records)
 */
class StoryFactory extends Factory
{
    protected $model = Story::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'title' => $this->faker->text(50),
            'content' => $this->faker->text(200),
        ];
    }
}
