<?php

namespace Lapaliv\BulkUpsert\Tests\Features;

use Faker\Factory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Collection;

class GenerateUserCollectionFeature
{
    private Generator $faker;

    public function __construct(private string $model)
    {
        $this->faker = Factory::create();
    }

    /**
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection<int, \Lapaliv\BulkUpsert\Tests\Models\User>
     */
    public function handle(int $limit): Collection
    {
        $collection = new Collection();

        for ($i = 0; $i < $limit; $i++) {
            $collection->push(
                new $this->model([
                    'email' => $this->faker->email(),
                    'name' => $this->faker->name(),
                    'phone' => $this->faker->phoneNumber(),
                ])
            );
        }

        return $collection;
    }
}