<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @internal
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
