<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Enums\Gender;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;

/**
 * @internal
 *
 * @method PostgreSqlUser|UserCollection create($attributes = [], ?Model $parent = null)
 * @method PostgreSqlUser|UserCollection make($attributes = [], ?Model $parent = null)
 * @method PostgreSqlUser|UserCollection createMany(iterable $records)
 */
final class PostgreSqlUserFactory extends Factory
{
    protected $model = PostgreSqlUser::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->uuid() . '@' . $this->faker->domainName(),
            'gender' => $this->faker->randomElement([
                Gender::male(),
                Gender::female(),
            ]),
            'avatar' => $this->faker->url(),
            'posts_count' => $this->faker->randomNumber(3),
            'is_admin' => $this->faker->boolean(),
            'balance' => $this->faker->randomElement([
                null,
                $this->faker->randomFloat(2, 0, 100_000),
            ]),
            'birthday' => $this->faker->randomElement([
                null,
                $this->faker->date(),
            ]),
            'phones' => [$this->faker->phoneNumber()],
            'last_visited_at' => $this->faker->randomElement([
                null,
                $this->faker->dateTimeBetween('-3 years'),
            ]),
            'update_uuid' => $this->faker->uuid(),
        ];
    }
}
