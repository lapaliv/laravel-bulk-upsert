<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Update;

use BadMethodCallException;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\App;
use Lapaliv\BulkUpsert\Collections\BulkRows;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Features\UserGenerator;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\SqLiteUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Support\TestCallback;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;
use Mockery;

/**
 * @internal
 */
final class UpdateBuilderCallbacksTest extends TestCase
{
    use UserTestTrait;

    /**
     * @param class-string<User> $model
     * @param string $method
     * @param Closure $callback
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider dataProvider
     */
    public function testModel(string $model, string $method, Closure $callback): void
    {
        // arrange
        $users = $callback();
        $spy = Mockery::spy(TestCallback::class, $method);
        $sut = $model::query()->bulk();
        $sut->{$method}($spy);

        // act
        $sut->update($users);

        // arrange
        $this->spyShouldHaveReceived($spy)
            ->once()
            ->withArgs(
                function (User $user) use ($users): bool {
                    return $user->id === $users->get(0)->id;
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
     * @throws BulkException
     *
     * @dataProvider dataProvider
     */
    public function testCollection(string $model, string $method, Closure $callback): void
    {
        // arrange
        /** @var UserCollection $users */
        $users = $callback();
        $spy = Mockery::spy(TestCallback::class, $method);
        $sut = $model::query()->bulk();
        $sut->{$method . 'Many'}($spy);

        // act
        $sut->update($users);

        // arrange
        $this->spyShouldHaveReceived($spy)
            ->once()
            ->withArgs(
                function (UserCollection $actualUsers, BulkRows $bulkRows) use ($users): bool {
                    return $actualUsers->count() === $users->count()
                        && $actualUsers->get(0)->id === $users->get(0)->id
                        && $bulkRows->count() === $users->count()
                        && $bulkRows->get(0)->model->id === $users->get(0)->id
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
            'onUpdating' => [
                'onUpdating',
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(1);
                },
            ],
            'onSaving' => [
                'onSaving',
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(1);
                },
            ],
            'onUpdated' => [
                'onUpdated',
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(1);
                },
            ],
            'onSaved' => [
                'onSaved',
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(1);
                },
            ],
            'onDeleting' => [
                'onDeleting',
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(
                            1,
                            ['deleted_at' => null],
                            ['deleted_at' => Carbon::now()],
                        );
                },
            ],
            'onDeleted' => [
                'onDeleted',
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(
                            1,
                            ['deleted_at' => null],
                            ['deleted_at' => Carbon::now()],
                        );
                },
            ],
            'onRestoring' => [
                'onRestoring',
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(
                            1,
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null],
                        );
                },
            ],
            'onRestored' => [
                'onRestored',
                function (string $model) {
                    return App::make(UserGenerator::class)
                        ->setModel($model)
                        ->createCollectionAndDirty(
                            1,
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null],
                        );
                },
            ],
        ];

        $result = [];

        foreach ($this->userModelsDataProvider() as $type => $model) {
            foreach ($target as $key => $value) {
                $result[$key . ' && ' . $type] = [
                    $model[0],
                    $value[0],
                    function () use ($model, $value) {
                        return $value[1]($model[0]);
                    },
                ];
            }
        }

        return $result;
    }

    public function userModelsDataProvider(): array
    {
        return [
            'mysql' => [MySqlUser::class],
            'pgsql' => [PostgreSqlUser::class],
            'sqlite' => [SqLiteUser::class],
        ];
    }
}
