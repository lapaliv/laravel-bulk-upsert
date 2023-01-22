<?php

namespace Lapaliv\BulkUpsert\Tests\App\Features;

use Carbon\Carbon;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Lapaliv\BulkUpsert\Tests\App\Models\User;

class GenerateUserTestFeature
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    /**
     * @throws Exception
     */
    public function handle(string $model, ?array $only = null, array $nullable = []): User
    {
        $result = [
            'email' => sprintf('%s@%s', $this->faker->uuid(), $this->faker->domainName),
            'name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'date' => Carbon::parse($this->faker->dateTime)->toDateString(),
            'microseconds' => Carbon::now()
                ->subSeconds(random_int(1, 999_999))
                ->format('Y-m-d H:i:s.u'),
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
