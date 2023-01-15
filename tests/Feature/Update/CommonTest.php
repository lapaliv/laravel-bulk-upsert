<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Update;

use Carbon\Carbon;
use Exception;
use Faker\Factory;
use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Tests\Collections\UserCollection;
use Lapaliv\BulkUpsert\Tests\Models\MysqlUser;
use Lapaliv\BulkUpsert\Tests\Models\PostgresUser;
use Lapaliv\BulkUpsert\Tests\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

class CommonTest extends TestCase
{
    private const NUMBER_OF_USERS = 3;

    /**
     * @dataProvider data
     * @param string $model
     * @return void
     * @throws Exception
     */
    public function test(string $model): void
    {
        [
            'existingUsers' => $existingUsers,
            'updatingUsers' => $updatingUsers,
            'sut' => $sut,
        ] = $this->arrange($model);

        // act
        $sut->update($model, $updatingUsers, ['email']);

        // assert
        foreach ($updatingUsers as $email => $user) {
            $this->assertDatabaseHas(
                $user->getTable(),
                $user->toArray(),
                $user->getConnection()->getName(),
            );

            $this->assertDatabaseMissing(
                $user->getTable(),
                $existingUsers->get($email)->toArray(),
                $user->getConnection()->getName(),
            );
        }
    }

    public function data(): array
    {
        return [
            [MysqlUser::class],
            [PostgresUser::class],
        ];
    }

    /**
     * @param string|User $model
     * @return array{
     *     existingUsers: UserCollection|User[],
     *     updatingUsers: UserCollection|User[],
     *     sut: BulkUpdate,
     * }
     * @throws Exception
     */
    private function arrange(string $model)
    {
        $users = $this->generateCollection($model)->keyBy('email');

        return [
            'existingUsers' => $model::query()
                ->whereIn('email', $users->keys())
                ->get()
                ->keyBy('email'),
            'updatingUsers' => $users->keyBy('email'),
            'sut' => $this->app->make(BulkUpdate::class),
        ];
    }

    /**
     * @param string|User $model
     * @return UserCollection
     * @throws Exception
     */
    private function generateCollection(string $model): UserCollection
    {
        $result = new UserCollection();

        for ($i = 0; $i < self::NUMBER_OF_USERS; $i++) {
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
