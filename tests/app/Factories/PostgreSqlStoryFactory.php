<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlStory;

class PostgreSqlStoryFactory extends Factory
{
    protected $model = PostgreSqlStory::class;

    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'title' => $this->faker->text(50),
            'content' => $this->faker->text(200),
        ];
    }
}
