<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Insert;

use Carbon\Carbon;
use Faker\Factory;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Tests\Collections\UserCollection;
use Lapaliv\BulkUpsert\Tests\Models\MysqlUser;
use Lapaliv\BulkUpsert\Tests\Models\PostgresUser;
use Lapaliv\BulkUpsert\Tests\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

class DateFieldsTest extends TestCase
{
    private const NUMBER_OF_USERS = 2;

    /**
     * @dataProvider data
     * @param string $model
     * @return void
     */
    public function test(string $model): void
    {
        [
            'sut' => $sut,
            'collection' => $collection,
        ] = $this->arrange($model);

        // act
        $sut->insert(['email'], $collection);

        // assert
        $collection->each(
            function (User $expectedUser): void {
                /** @var User $actualUser */
                $actualUser = $expectedUser->newQuery()
                    ->where('email', $expectedUser->email)
                    ->first();

                self::assertEquals(
                    $expectedUser->date->startOfDay()->format('Y-m-d H:i:s.u'),
                    $actualUser->date->format('Y-m-d H:i:s.u')
                );
            }
        );
    }

    public function data(): array
    {
        return [
            [MysqlUser::class],
            [PostgresUser::class],
        ];
    }

    /**
     * @param string $model
     * @return array{
     *     sut: BulkInsert,
     *     collection: UserCollection
     * }
     */
    private function arrange(string $model): array
    {
        $collection = $this->generateCollection($model);
        $sut = new BulkInsert($model);

        return compact('collection', 'sut');
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
                    'date' => Carbon::parse($faker->dateTime()),
                ]),
            );
        }

        return $result;
    }
}