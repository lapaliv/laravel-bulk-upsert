<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlPost;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;

/**
 * @internal
 */
abstract class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => PostgreSqlUser::factory(),
            'post_id' => PostgreSqlPost::factory(),
            'text' => $this->faker->text(),
        ];
    }
}
