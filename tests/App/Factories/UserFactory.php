<?php

namespace Tests\App\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\App\Enums\Gender;
use Tests\App\Models\User;

/**
 * The factory which builds {@see \Tests\App\Models\User} test models.
 *
 * The user is the main fixture of the suite. It has a column of almost every type, so
 * the tests can check how the package writes and reads them. Some columns are filled
 * with `null` from time to time, which keeps the nullable cases covered as well.
 *
 * @internal
 *
 * @method \Tests\App\Models\User|\Tests\App\Collection\UserCollection create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 * @method \Tests\App\Models\User|\Tests\App\Collection\UserCollection make($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
 * @method \Tests\App\Models\User|\Tests\App\Collection\UserCollection createMany(iterable $records)
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Tests\App\Models\User>
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * The apostrophes are removed from the generated name.
     *
     * @return array<string, mixed>
     */
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
