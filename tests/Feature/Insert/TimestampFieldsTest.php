<?php

namespace Lapaliv\BulkUpsert\Tests\Feature\Insert;

use Carbon\Carbon;
use DateTime;
use Faker\Factory;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Tests\App\Collections\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

class TimestampFieldsTest extends TestCase
{
    private const NUMBER_OF_USERS = 2;

    /**
     * @dataProvider data
     * @param string $model
     * @return void
     */
    public function test(string $model): void
    {
        // arrange
        $collection = $this->generateCollection($model);
        $sut = $this->app->make(BulkInsert::class);

        // act
        $sut->insert($model, ['email'], $collection);

        // assert
        $collection->each(
            function (User $expectedUser): void {
                /** @var User $actualUser */
                $actualUser = $expectedUser->newQuery()
                    ->where('email', $expectedUser->email)
                    ->first();

                self::assertEquals(
                    $expectedUser->microseconds->format('Y-m-d H:i:s.u'),
                    $actualUser->microseconds->format('Y-m-d H:i:s.u')
                );
            }
        );
    }

    public function data(): array
    {
        return [
            [MySqlUser::class],
            [PostgreSqlUser::class],
        ];
    }

    private function generateCollection(string $model): UserCollection
    {
        $faker = Factory::create();
        $result = new UserCollection();

        for ($i = 0; $i < self::NUMBER_OF_USERS; $i++) {
            $result->push(
                new $model([
                    'email' => $faker->unique()->email(),
                    'name' => $faker->name(),
                    'microseconds' => Carbon::parse(new DateTime()),
                ]),
            );
        }

        return $result;
    }
}
