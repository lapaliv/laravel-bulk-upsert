<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collections\MySqlUserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;

/**
 * @method MySqlUser|MySqlUserCollection make($attributes = [], ?Model $parent = null)
 * @method MySqlUser|MySqlUserCollection create($attributes = [], ?Model $parent = null)
 */
class MySqlUserFactory extends Factory
{
    protected $model = MySqlUser::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->uuid() . '@' . $this->faker->domainName,
        ];
    }
}
