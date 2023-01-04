<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Insert;

use Faker\Factory;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Tests\Models\MysqlUser;
use Lapaliv\BulkUpsert\Tests\Models\PostgresUser;
use Lapaliv\BulkUpsert\Tests\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

class SelectColumnsWithIncrementingTest extends TestCase
{
    private const NUMBER_OF_USERS = 3;

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
        // This part is described in the `assertChunk` method
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
     *     sut: \Lapaliv\BulkUpsert\BulkInsert,
     *     collection: Collection
     * }
     */
    private function arrange(string $model): array
    {
        // todo: вынести генерацию коллекции
        $faker = Factory::create();
        $collection = new Collection();

        for ($i = 0; $i < self::NUMBER_OF_USERS; $i++) {
            $collection->push(
                new $model([
                    'email' => $faker->email(),
                    'name' => $faker->name(),
                ])
            );
        }

        $actualSelectColumns = ['email', 'name'];
        $expectSelectColumns = [...$actualSelectColumns, (new $model)->getKeyName()];

        $sut = new BulkInsert($model);
        $sut->select($actualSelectColumns)
            ->onInserted(
                fn(Collection $users) => $this->assertChunk($users, $expectSelectColumns)
            );

        return compact('sut', 'collection');
    }

    public function assertChunk(Collection $users, array $expectSelectColumns): void
    {
        $users->each(
            function (User $user) use ($expectSelectColumns): void {
                $diff = array_diff(
                    array_keys($user->getAttributes()),
                    $expectSelectColumns
                );

                self::assertEmpty($diff, 'User has extra columns');
            }
        );
    }
}