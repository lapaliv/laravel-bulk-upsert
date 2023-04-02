<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Enums\Gender;
use Lapaliv\BulkUpsert\Tests\App\Models\User;

/**
 * @internal
 *
 * @method User|UserCollection create($attributes = [], ?Model $parent = null)
 * @method User|UserCollection make($attributes = [], ?Model $parent = null)
 * @method User|UserCollection createMany(iterable $records)
 */
abstract class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->randomLetter() . Carbon::now()->format('Uu') . '@' . $this->faker->domainName(),
            'gender' => $this->faker->randomElement([
                Gender::male(),
                Gender::female(),
            ]),
            'avatar' => $this->faker->imageUrl(),
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
                $this->faker->dateTime(),
            ]),
            'update_uuid' => $this->faker->uuid(),
        ];
    }
}
