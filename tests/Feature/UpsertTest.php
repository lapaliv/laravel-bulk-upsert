<?php

namespace Lapaliv\BulkUpsert\Tests\Feature;

use Exception;
use Lapaliv\BulkUpsert\BulkUpsert;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\App\Collections\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateSpyListenersTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateUserCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\SaveAndFillUserCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\SetUserEventSpyListenersTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\User;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Mockery\VerificationDirector;

class UpsertTest extends TestCase
{
    private GenerateUserCollectionTestFeature $generateUserCollectionFeature;
    private SaveAndFillUserCollectionTestFeature $saveAndFillUserCollectionTestFeature;
    private SetUserEventSpyListenersTestFeature $setUserEventSpyListenersTestFeature;
    private GenerateSpyListenersTestFeature $generateSpyListenersTestFeature;

    /**
     * @param string $model
     * @return void
     * @throws Exception
     * @dataProvider users
     */
    public function testSuccessful(string $model): void
    {
        // arrange
        /** @var BulkUpsert $sut */
        $sut = $this->app->make(BulkUpsert::class);
        $users = $this->generateUserCollectionFeature->handle($model, 4);
        $this->saveAndFillUserCollectionTestFeature->handle($users, 2);

        // act
        $sut->upsert($model, $users, ['email']);

        // assert
        $users->each(
            function (User $user) {
                $this->assertDatabaseHas(
                    $user->getTable(),
                    [
                        'email' => $user->email,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'date' => $user->date->toDateString(),
                        'microseconds' => $user->microseconds->format('Y-m-d H:i:s.u'),
                    ],
                    $user->getConnectionName(),
                );

                $this->assertDatabaseMissing(
                    $user->getTable(),
                    [
                        'email' => $user->email,
                        'created_at' => null,
                        'updated_at' => null,
                    ],
                    $user->getConnectionName(),
                );
            }
        );
    }

    /**
     * @param string $model
     * @return void
     * @throws Exception
     * @dataProvider users
     */
    public function testWithoutInserting(string $model): void
    {
        // arrange
        /** @var BulkUpsert $sut */
        $sut = $this->app->make(BulkUpsert::class);
        $users = $this->generateUserCollectionFeature->handle($model, 4);
        $this->saveAndFillUserCollectionTestFeature->handle($users, 4);

        // act
        $sut->upsert($model, $users, ['email']);

        // assert
        $users->each(
            function (User $user) {
                $this->assertDatabaseHas(
                    $user->getTable(),
                    [
                        'id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'date' => $user->date->toDateString(),
                        'microseconds' => $user->microseconds->format('Y-m-d H:i:s.u'),
                    ],
                    $user->getConnectionName(),
                );
            }
        );
    }

    /**
     * @param string $model
     * @return void
     * @throws Exception
     * @dataProvider users
     */
    public function testWithoutUpdating(string $model): void
    {
        // arrange
        /** @var BulkUpsert $sut */
        $sut = $this->app->make(BulkUpsert::class);
        $users = $this->generateUserCollectionFeature->handle($model, 4);

        // act
        $sut->upsert($model, $users, ['email']);

        // assert
        $users->each(
            function (User $user) {
                $this->assertDatabaseHas(
                    $user->getTable(),
                    [
                        'email' => $user->email,
                        'name' => $user->name,
                        'phone' => $user->phone,
                        'date' => $user->date->toDateString(),
                        'microseconds' => $user->microseconds->format('Y-m-d H:i:s.u'),
                    ],
                    $user->getConnectionName(),
                );
            }
        );
    }

    /**
     * @param string $model
     * @return void
     * @throws Exception
     * @dataProvider users
     */
    public function testEvents(string $model): void
    {
        // arrange
        /** @var BulkUpsert $sut */
        $sut = $this->app->make(BulkUpsert::class);
        $users = $this->generateUserCollectionFeature->handle($model, 4);
        $this->saveAndFillUserCollectionTestFeature->handle($users, 2);
        $listeners = $this->generateSpyListenersTestFeature->handle();
        $this->setUserEventSpyListenersTestFeature->handle($model, $listeners);

        // act
        $sut->upsert($model, $users, ['email']);

        // assert
        foreach ([BulkEventEnum::CREATING, BulkEventEnum::CREATED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->times(2)
                ->withArgs(
                    function (User $user) use ($users): bool {
                        return $users->slice(2)
                            ->where('email', $user->email)
                            ->isNotEmpty();
                    }
                );
        }

        foreach ([BulkEventEnum::UPDATING, BulkEventEnum::UPDATED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->times(2)
                ->withArgs(
                    function (User $user) use ($users): bool {
                        return $users->slice(0, 2)
                            ->where('email', $user->email)
                            ->isNotEmpty();
                    }
                );
        }

        foreach ([BulkEventEnum::SAVING, BulkEventEnum::SAVED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->times($users->count())
                ->withAnyArgs();
        }
    }

    /**
     * @param string $model
     * @return void
     * @throws Exception
     * @dataProvider users
     */
    public function testCallbacks(string $model): void
    {
        // arrange
        $users = $this->generateUserCollectionFeature->handle($model, 4);
        $this->saveAndFillUserCollectionTestFeature->handle($users, 2);
        $listeners = $this->generateSpyListenersTestFeature->handle();
        /** @var BulkUpsert $sut */
        $sut = $this->app->make(BulkUpsert::class);
        $sut->onCreating($listeners[BulkEventEnum::CREATING])
            ->onCreated($listeners[BulkEventEnum::CREATED])
            ->onUpdating($listeners[BulkEventEnum::UPDATING])
            ->onUpdated($listeners[BulkEventEnum::UPDATED])
            ->onSaving($listeners[BulkEventEnum::SAVING])
            ->onSaved($listeners[BulkEventEnum::SAVED]);

        // act
        $sut->upsert($model, $users, ['email']);

        // assert
        foreach ([BulkEventEnum::CREATING, BulkEventEnum::CREATED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->once()
                ->withArgs(
                    function (UserCollection $collection) use ($users): bool {
                        if ($collection->count() !== 2) {
                            return false;
                        }

                        $expectedEmails = $users->slice(2)->pluck('email')->sort()->join(',');
                        $actualEmails = $collection->pluck('email')->sort()->join(',');

                        return $expectedEmails === $actualEmails;
                    }
                );
        }

        foreach ([BulkEventEnum::UPDATING, BulkEventEnum::UPDATED, BulkEventEnum::SAVING] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->once()
                ->withArgs(
                    function (UserCollection $collection) use ($users): bool {
                        if ($collection->count() !== 2) {
                            return false;
                        }

                        $expectedEmails = $users->slice(0, 2)->pluck('email')->sort()->join(',');
                        $actualEmails = $collection->pluck('email')->sort()->join(',');

                        return $expectedEmails === $actualEmails;
                    }
                );
        }

        /** @var VerificationDirector $callback */
        $callback = $listeners[BulkEventEnum::SAVED]->shouldHaveReceived('__invoke');
        $callback->twice()
            ->withArgs(
                function (UserCollection $collection): bool {
                    return $collection->count() === 2;
                }
            );
    }

    /**
     * Data provider.
     *
     * @return string[][]
     */
    public function users(): array
    {
        return [
            [MySqlUser::class]
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateUserCollectionFeature = $this->app->make(GenerateUserCollectionTestFeature::class);
        $this->saveAndFillUserCollectionTestFeature = $this->app->make(SaveAndFillUserCollectionTestFeature::class);
        $this->setUserEventSpyListenersTestFeature = $this->app->make(SetUserEventSpyListenersTestFeature::class);
        $this->generateSpyListenersTestFeature = $this->app->make(GenerateSpyListenersTestFeature::class);
    }
}
