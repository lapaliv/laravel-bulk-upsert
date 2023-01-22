<?php

namespace Lapaliv\BulkUpsert\Tests\App\Features;

use Carbon\Carbon;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Lapaliv\BulkUpsert\Tests\App\Models\Entity;

class GenerateEntityTestFeature
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    /**
     * @param class-string<Entity> $model
     * @param array|null $only
     * @param array $nullable
     * @return Entity
     * @throws Exception
     */
    public function handle(string $model, ?array $only = null, array $nullable = []): Entity
    {
        $result = [
            'uuid' => $this->faker->uuid(),
            'string' => $this->faker->text(),
            'nullable_string' => $this->faker->randomElement([
                null,
                $this->faker->text(),
            ]),
            'integer' => $this->faker->numberBetween(),
            'nullable_integer' => $this->faker->randomElement([
                null,
                $this->faker->numberBetween(),
            ]),
            'decimal' => $this->faker->randomFloat(3, 0, 999),
            'nullable_decimal' => $this->faker->randomElement([
                null,
                $this->faker->randomFloat(3, 0, 999),
            ]),
            'boolean' => $this->faker->boolean(),
            'nullable_boolean' => $this->faker->randomElement([
                null,
                $this->faker->boolean(),
            ]),
            'json' => $this->faker->rgbColorAsArray(),
            'nullable_json' => $this->faker->randomElement([
                null,
                $this->faker->rgbColorAsArray()
            ]),
            'date' => $this->faker->date(),
            'nullable_date' => $this->faker->randomElement([
                null,
                $this->faker->date(),
            ]),
            'custom_datetime' => $this->faker->date(Entity::CUSTOM_DATE_FORMAT),
            'nullable_custom_datetime' => $this->faker->randomElement([
                null,
                $this->faker->date(Entity::CUSTOM_DATE_FORMAT),
            ]),
            'microseconds' => Carbon::now()
                ->subSeconds($this->faker->numberBetween(0, 999_999))
                ->format(Entity::MICROSECONDS_FORMAT),
            'nullable_microseconds' => $this->faker->randomElement([
                null,
                Carbon::now()
                    ->subSeconds($this->faker->numberBetween(0, 999_999))
                    ->format(Entity::MICROSECONDS_FORMAT),
            ]),
        ];

        if ($only !== null) {
            $result = array_filter(
                $result,
                static fn (mixed $value, string $key) => in_array($key, $only, true),
                ARRAY_FILTER_USE_BOTH
            );
        }

        foreach ($nullable as $field) {
            $result[$field] = null;
        }

        return new $model($result);
    }
}
