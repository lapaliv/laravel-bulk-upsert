<?php

namespace Lapaliv\BulkUpsert\Tests\Feature;

use Exception;
use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateUserCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;

class UpdateTest extends TestCase
{
    private GenerateUserCollectionTestFeature $generateUserCollectionFeature;

    /**
     * @param string $model
     * @return void
     * @throws Exception
     * @dataProvider models
     */
    public function testSuccess(string $model): void
    {
        // arrange
        /** @var BulkUpdate $sut */
        $sut = $this->app->make(BulkUpdate::class);
        $newUsers = $this->generateUserCollectionFeature->handle($model, 3);
        $actualUsers = $this->generateUserCollectionFeature->handle($model, 3)
            ->each(fn (User $user) => $user->save());
        $expectedUsers = $newUsers->map(
            function (User $user, int $key) use ($actualUsers) {
                $user->id = $actualUsers->get($key)->id;
                $user->email = $actualUsers->get($key)->email;

                return $user;
            }
        );


        // act
        $sut->update($model, $expectedUsers);

        // assert
        $actualUsers->each(
            fn (User $user) => $this->assertDatabaseMissing(
                $user->getTable(),
                [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'date' => $user->date->toDateString(),
                    'microseconds' => $user->microseconds->format('Y-m-d H:i:s.u')
                ],
                $user->getConnectionName()
            )
        );
        $expectedUsers->each(
            fn (User $user) => $this->assertDatabaseHas(
                $user->getTable(),
                [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'date' => $user->date->toDateString(),
                    'microseconds' => $user->microseconds->format('Y-m-d H:i:s.u')
                ],
                $user->getConnectionName()
            )
        );
    }

    public function models(): array
    {
        return [
            [MySqlUser::class],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateUserCollectionFeature = $this->app->make(GenerateUserCollectionTestFeature::class);
    }
}
