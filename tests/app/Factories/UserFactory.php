<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lapaliv\BulkUpsert\Tests\App\Enums\Gender;

/**
 * @internal
 */
abstract class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => str_replace('\'', '', $this->faker->name()),
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
