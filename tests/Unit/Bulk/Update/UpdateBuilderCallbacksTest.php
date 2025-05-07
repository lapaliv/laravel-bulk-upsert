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
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\App\Support\TestCallback;
use Lapaliv\BulkUpsert\Tests\TestCaseWrapper;
use Lapaliv\BulkUpsert\Tests\Unit\UserTestTrait;
use Mockery;

/**
 * @internal
 */
final class UpdateBuilderCallbacksTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @param string $method
     * @param Closure $callback
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider dataProvider
     */
    public function testModel(string $method, Closure $callback): void
    {
        // arrange
        $users = $callback();
        $spy = Mockery::spy(TestCallback::class, $method);
        $sut = User::query()->bulk();
        $sut->{$method}($spy);

        // act
        $sut->update($users);

        // arrange
        self::spyShouldHaveReceived($spy)
            ->once()
            ->withArgs(
                function (User $user) use ($users): bool {
                    return $user->id === $users->get(0)->id;
                }
            );
    }

    /**
     * @param string $method
     * @param Closure $callback
     *
     * @return void
     *
     * @throws BulkException
     *
     * @dataProvider dataProvider
     */
    public function testCollection(string $method, Closure $callback): void
    {
        // arrange
        /** @var UserCollection $users */
        $users = $callback();
        $spy = Mockery::spy(TestCallback::class, $method);
        $sut = User::query()->bulk();
        $sut->{$method . 'Many'}($spy);

        // act
        $sut->update($users);

        // arrange
        self::spyShouldHaveReceived($spy)
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

    public function testCallUndefinedListener(): void
    {
        // arrange
        $sut = User::query()->bulk();

        // assert
        $this->expectException(BadMethodCallException::class);

        // act
        $sut->onFake();
    }

    public function dataProvider(): array
    {
        return [
            'onUpdating' => [
                'onUpdating',
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(1);
                },
            ],
            'onSaving' => [
                'onSaving',
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(1);
                },
            ],
            'onUpdated' => [
                'onUpdated',
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(1);
                },
            ],
            'onSaved' => [
                'onSaved',
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(1);
                },
            ],
            'onDeleting' => [
                'onDeleting',
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(
                            1,
                            ['deleted_at' => null],
                            ['deleted_at' => Carbon::now()],
                        );
                },
            ],
            'onDeleted' => [
                'onDeleted',
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(
                            1,
                            ['deleted_at' => null],
                            ['deleted_at' => Carbon::now()],
                        );
                },
            ],
            'onRestoring' => [
                'onRestoring',
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(
                            1,
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null],
                        );
                },
            ],
            'onRestored' => [
                'onRestored',
                function () {
                    return App::make(UserGenerator::class)
                        ->createCollectionAndDirty(
                            1,
                            ['deleted_at' => Carbon::now()],
                            ['deleted_at' => null],
                        );
                },
            ],
        ];
    }
}
