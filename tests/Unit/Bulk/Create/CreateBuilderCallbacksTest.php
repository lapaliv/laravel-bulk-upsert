<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Bulk\Create;

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
final class CreateBuilderCallbacksTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * @param string $method
     * @param Closure $callback
     *
     * @return void
     *
     * @dataProvider dataProvider
     *
     * @throws BulkException
     */
    public function testModel(string $method, Closure $callback): void
    {
        // arrange
        $users = $callback();
        $spy = Mockery::spy(TestCallback::class, $method);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);
        $sut->{$method}($spy);

        // act
        $sut->create($users);

        // arrange
        self::spyShouldHaveReceived($spy)
            ->once()
            ->withArgs(
                function (User $user) use ($users): bool {
                    return $user->email === $users->get(0)->email;
                }
            );
    }

    /**
     * @param string $method
     * @param Closure $callback
     *
     * @return void
     *
     * @dataProvider dataProvider
     *
     * @throws BulkException
     */
    public function testCollection(string $method, Closure $callback): void
    {
        // arrange
        /** @var UserCollection $users */
        $users = $callback();
        $spy = Mockery::spy(TestCallback::class, $method);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email']);
        $sut->{$method . 'Many'}($spy);

        // act
        $sut->create($users);

        // arrange
        self::spyShouldHaveReceived($spy)
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
     * @return void
     *
     * @throws BulkException
     */
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
    }
}
