<?php

namespace Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\App\Models\Story;

/**
 * The factory which builds {@see \Tests\App\Models\Story} test models.
 *
 * A story has a uuid as its primary key and no relations at all. The tests use it for
 * the cases where the package has to return the created models back.
 *
 * @internal
 *
 * @method \Tests\App\Models\Story|\Tests\App\Collection\StoryCollection create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 * @method \Tests\App\Models\Story|\Tests\App\Collection\StoryCollection make($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 * @method \Tests\App\Models\Story|\Tests\App\Collection\StoryCollection createMany(iterable $records)
 */
class StoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Tests\App\Models\Story>
     */
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
