<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Create;

use BadMethodCallException;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\App;
use Lapaliv\BulkUpsert\Collection\BulkRows;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Features\UserGenerator;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Support\TestCallback;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;
use Mockery;

/**
 * @internal
 */
final class CreateBuilderCallbacksTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     * @param string $method
     * @param Closure $callback
     *
     * @return void
     *
     * @dataProvider dataProvider
     */
    public function testModel(string $model, string $method, Closure $callback): void
    {
        // arrange
        $users = $callback();
        $spy = Mockery::spy(TestCallback::class, $method);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['email']);
        $sut->{$method}($spy);

        // act
        $sut->create($users);

        // arrange
        $this->spyShouldHaveReceived($spy)
            ->once()
            ->withArgs(
                function (User $user) use ($users): bool {
                    return $user->email === $users->get(0)->email;
                }
            );
    }

    /**
     * @param class-string<User> $model
     * @param string $method
     * @param Closure $callback
     *
     * @return void
     *
     * @dataProvider dataProvider
     */
    public function testCollection(string $model, string $method, Closure $callback): void
    {
        // arrange
        /** @var UserCollection $users */
        $users = $callback();
        $spy = Mockery::spy(TestCallback::class, $method);
        $sut = $model::query()
            ->bulk()
            ->uniqueBy(['email']);
        $sut->{$method . 'Many'}($spy);

        // act
        $sut->create($users);

        // arrange
        $this->spyShouldHaveReceived($spy)
            ->once()
            ->withArgs(
                function (UserCollection $actualUsers, BulkRows $bulkRows) use ($users): bool {
                    return $actualUsers->count() === $users->count()
                        && $actualUsers->get(0)->email === $users->get(0)->email
                        && $bulkRows->count() === $users->count()
                        && $bulkRows->get(0)->model->email === $users->get(0)->email
                        && $bulkRows->get(0)->original === $users->get(0);
                }
            );
    }

    /**
     * @param class-string<User> $model
     *
     * @return void
     *
     * @dataProvider userModelsDataProvider
     */
    public function testCallUndefinedListener(string $model): void
    {
        // arrange
        $sut = $model::query()->bulk();

        // assert
        $this->expectException(BadMethodCallException::class);

        // act
        $sut->onFake();
    }

    public function dataProvider(): array
    {
        $target = [
            'onCreating' => [
                'onCreating',
                function () {
                    return App::make(UserGenerator::class)->makeCollection(1);
                },
            ],
            'onSaving' => [
                'onSaving',
                function () {
                    return App::make(UserGenerator::class)->makeCollection(1);
                },
            ],
            'onCreated' => [
                'onCreated',
                function () {
                    return App::make(UserGenerator::class)->makeCollection(1);
                },
            ],
            'onSaved' => [
                'onSaved',
                function () {
                    return App::make(UserGenerator::class)->makeCollection(1);
                },
            ],
            'onDeleting' => [
                'onDeleting',
                function () {
                    return App::make(UserGenerator::class)->makeCollection(
                        1,
                        ['deleted_at' => Carbon::now()],
                    );
                },
            ],
            'onDeleted' => [
                'onDeleted',
                function () {
                    return App::make(UserGenerator::class)->makeCollection(
                        1,
                        ['deleted_at' => Carbon::now()],
                    );
                },
            ],
        ];

        $result = [];

        foreach ($target as $key => $value) {
            foreach ($this->userModelsDataProvider() as $type => $model) {
                $result[$key . '&& ' . $type] = [
                    $model[0],
                    ...$value,
                ];
            }
        }

        return $result;
    }

    public function userModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class],
            'postgre' => [PostgreSqlUser::class],
        ];
    }
}
