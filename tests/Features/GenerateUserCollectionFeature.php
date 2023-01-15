<?php

namespace Lapaliv\BulkUpsert\Tests\Features;

use Carbon\Carbon;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Collection;

class GenerateUserCollectionFeature
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    /**
     * @param string $model
     * @param int $limit
     * @param array|null $only
     * @param array $nullable
     * @return Collection
     * @throws Exception
     */
    public function handle(string $model, int $limit, ?array $only = null, array $nullable = []): Collection
    {
        $collection = new Collection();

        for ($i = 0; $i < $limit; $i++) {
            $collection->push(
                new $model($this->generateUserData($only, $nullable))
            );
        }

        return $collection;
    }

    /**
     * @return array{
     *     email: string,
     *     name: string,
     *     phone: string,
     *     date: string,
     *     microseconds: string,
     * }
     * @throws Exception
     */
    private function generateUserData(?array $only = null, array $nullable = []): array
    {
        $result = [
            'email' => $this->faker->uuid() . '-' . $this->faker->email(),
            'name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'date' => Carbon::parse($this->faker->dateTime)->toDateString(),
            'microseconds' => Carbon::now()
                ->subSeconds(
                    random_int(1, 999_999)
                )
                ->format('Y-m-d H:i:s.u'),
        ];

        if ($only !== null) {
            $result = array_filter(
                $result,
                static fn(mixed $value, string $key) => in_array($key, $only, true),
                ARRAY_FILTER_USE_BOTH
            );
        }

        foreach ($nullable as $field) {
            $result[$field] = null;
        }

        return $result;
    }
}
