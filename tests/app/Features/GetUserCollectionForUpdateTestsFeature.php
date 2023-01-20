<?php

namespace Lapaliv\BulkUpsert\Tests\App\Features;

use Carbon\Carbon;
use Exception;
use Faker\Factory;
use Lapaliv\BulkUpsert\Tests\App\Collections\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\User;

class GetUserCollectionForUpdateTestsFeature
{
    public function handle(string $model, int $count)
    {
        /** @var User|string $model */

        $result = new UserCollection();

        for ($i = 0; $i < $count; $i++) {
            /** @var User $user */
            $user = $model::query()->create(
                $this->generateUserData()
            );

            // change some values
            $user->fill(
                $this->generateUserData()
            );

            $user->email = $user->getOriginal('email');

            $result->push($user);
        }

        return $result;
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
    private function generateUserData(): array
    {
        $faker = Factory::create();

        return [
            'email' => $faker->uuid() . '@example.com',
            'name' => $faker->name(),
            'phone' => $faker->phoneNumber(),
            'date' => Carbon::parse($faker->dateTime)->toDateString(),
            'microseconds' => Carbon::now()
                ->subSeconds(
                    random_int(1, 999_999)
                )
                ->format('Y-m-d H:i:s.u'),
        ];
    }
}
