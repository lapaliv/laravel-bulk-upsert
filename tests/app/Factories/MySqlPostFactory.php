<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\PostCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlPost;

/**
 * @internal
 *
 * @method MySqlPost|PostCollection create($attributes = [], ?Model $parent = null)
 * @method MySqlPost|PostCollection make($attributes = [], ?Model $parent = null)
 * @method MySqlPost|PostCollection createMany(iterable $records)
 */
final class MySqlPostFactory extends Factory
{
    protected $model = MySqlPost::class;

    public function definition(): array
    {
        return [
            'text' => $this->faker->text(),
        ];
    }
}
