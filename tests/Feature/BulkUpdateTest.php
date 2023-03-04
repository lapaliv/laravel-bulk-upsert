<?php

namespace Lapaliv\BulkUpsert\Tests\Feature;

use Carbon\Carbon;
use Exception;
use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\App\Collections\EntityCollection;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateEntityCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateSpyListenersTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\SaveAndFillEntityCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\SetModelEventSpyListenersTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\Entity;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithAutoIncrement;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithoutAutoIncrement;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Support\Callback;
use Lapaliv\BulkUpsert\Tests\App\Traits\CheckEntityInDatabase;
use Lapaliv\BulkUpsert\Tests\FeatureTestCase;
use Mockery;
use Mockery\VerificationDirector;

final class BulkUpdateTest extends FeatureTestCase
{
    use CheckEntityInDatabase;

    private GenerateEntityCollectionTestFeature $generateEntityCollectionTestFeature;
    private SaveAndFillEntityCollectionTestFeature $saveAndFillEntityCollectionTestFeature;
    private SetModelEventSpyListenersTestFeature $setModelEventSpyListenersTestFeature;
    private GenerateSpyListenersTestFeature $generateSpyListenersTestFeature;

    /**
     * @param class-string<Entity> $model
     * @return void
     * @throws Exception
     * @dataProvider entities
     */
    public function testSuccessful(string $model): void
    {
        // arrange
        /** @var BulkUpdate $sut */
        $sut = $this->app->make(BulkUpdate::class);
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 3);
        $this->saveAndFillEntityCollectionTestFeature->handle($entities, 3);

        // act
        $sut->update($model, $entities);

        // assert
        $entities->each(
            fn (Entity $entity) => $this->assertDatabaseHasEntity($entity),
        );
    }

    /**
     * @param class-string<Entity> $model
     * @return void
     * @throws Exception
     * @dataProvider entities
     */
    public function testEvents(string $model): void
    {
        // arrange
        /** @var BulkUpdate $sut */
        $sut = $this->app->make(BulkUpdate::class);
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 4);
        $this->saveAndFillEntityCollectionTestFeature->handle($entities, 4);
        $listeners = $this->generateSpyListenersTestFeature->handle();
        $this->setModelEventSpyListenersTestFeature->handle($model, $listeners);

        // act
        $sut->update($model, $entities);

        // assert
        foreach ([BulkEventEnum::CREATING, BulkEventEnum::CREATED] as $event) {
            $listeners[$event]->shouldNotHaveReceived('__invoke');
        }

        foreach ([BulkEventEnum::UPDATING, BulkEventEnum::UPDATED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->times($entities->count())->withAnyArgs();
        }

        foreach ([BulkEventEnum::SAVING, BulkEventEnum::SAVED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->times($entities->count())->withAnyArgs();
        }
    }

    /**
     * @param class-string<Entity> $model
     * @return void
     * @throws Exception
     * @dataProvider entities
     */
    public function testCallbacks(string $model): void
    {
        // arrange
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 4);
        $this->saveAndFillEntityCollectionTestFeature->handle($entities, 4);
        $listeners = $this->generateSpyListenersTestFeature->handle();
        /** @var BulkUpdate $sut */
        $sut = $this->app->make(BulkUpdate::class);
        $sut->onUpdating($listeners[BulkEventEnum::UPDATING])
            ->onUpdated($listeners[BulkEventEnum::UPDATED])
            ->onSaving($listeners[BulkEventEnum::SAVING])
            ->onSaved($listeners[BulkEventEnum::SAVED]);

        // act
        $sut->update($model, $entities);

        // assert
        foreach ([BulkEventEnum::CREATING, BulkEventEnum::CREATED] as $event) {
            $listeners[$event]->shouldNotHaveReceived('__invoke');
        }

        foreach ([BulkEventEnum::UPDATING, BulkEventEnum::UPDATED, BulkEventEnum::SAVING, BulkEventEnum::SAVED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->once()
                ->withArgs(
                    function (EntityCollection $collection) use ($entities): bool {
                        return $entities->count() === $collection->count();
                    }
                );
        }
    }

    /**
     * @param class-string<Entity> $model
     * @return void
     * @throws Exception
     * @dataProvider entities
     */
    public function testChunk(string $model): void
    {
        // arrange
        $chunkSize = 2;
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 7);
        $this->saveAndFillEntityCollectionTestFeature->handle($entities, 7);
        $callback = Mockery::spy(Callback::class);
        /** @var BulkUpdate $sut */
        $sut = $this->app->make(BulkUpdate::class);
        $sut->chunk($chunkSize, $callback);

        // act
        $sut->update($model, $entities);

        // assert
        /** @var VerificationDirector $spy */
        $spy = $callback->shouldHaveReceived('__invoke');
        $spy->times(4)
            ->withArgs(
                function (EntityCollection $chunk) use ($chunkSize): bool {
                    return $chunk->count() <= $chunkSize;
                }
            );
    }

    /**
     * @param class-string<Entity> $model
     * @return void
     * @throws Exception
     * @dataProvider entities
     */
    public function testUpdatingAttributes(string $model): void
    {
        // arrange
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 2);
        $this->saveAndFillEntityCollectionTestFeature->handle($entities, 2);
        /** @var BulkUpdate $sut */
        $sut = $this->app->make(BulkUpdate::class);

        // act
        $sut->update($model, $entities, null, ['string', 'integer', 'json', 'date', 'custom_datetime']);

        // assert
        $entities->each(
            function (Entity $entity) {
                $this->assertDatabaseHasEntity($entity, only: ['id', 'uuid', 'string', 'integer', 'json', 'date', 'custom_datetime']);
                $this->assertDatabaseMissingEntity($entity, except: ['string', 'integer', 'json', 'date', 'custom_datetime']);
            }
        );
    }

    public function testDispatchDeleteEvents(): void
    {
        // arrange
        $oldUser = MySqlUser::factory()->create();
        $newUser = MySqlUser::factory()->make([
            'email' => $oldUser->email,
            'deleted_at' => Carbon::now()->subDay(),
        ]);
        $deletingSpy = Mockery::spy(Callback::class);
        $deletedSpy = Mockery::spy(Callback::class);
        MySqlUser::deleting($deletingSpy);
        MySqlUser::deleted($deletedSpy);
        /** @var BulkUpdate $sut */
        $sut = $this->app->make(BulkUpdate::class);

        // act
        $sut->update(MySqlUser::class, [$newUser], ['email']);

        // assert
        $this->assertDatabaseHas(MySqlUser::table(), [
            'name' => $newUser->name,
            'email' => $newUser->email,
            'deleted_at' => $newUser->deleted_at->toDateTimeString(),
        ], $newUser->getConnectionName());
        $deletingSpy->shouldHaveReceived('__invoke');
        $deletedSpy->shouldHaveReceived('__invoke');
    }

    public function testRunDeleteCallbacks(): void
    {
        // arrange
        $oldUser = MySqlUser::factory()->create();
        $newUser = MySqlUser::factory()->make([
            'email' => $oldUser->email,
            'deleted_at' => Carbon::now()->subDay(),
        ]);
        $deletingSpy = Mockery::spy(Callback::class);
        $deletedSpy = Mockery::spy(Callback::class);
        /** @var BulkUpdate $sut */
        $sut = $this->app->make(BulkUpdate::class)
            ->onDeleting($deletingSpy)
            ->onDeleted($deletedSpy);

        // act
        $sut->update(MySqlUser::class, [$newUser], ['email']);

        // assert
        $deletingSpy->shouldHaveReceived('__invoke');
        $deletedSpy->shouldHaveReceived('__invoke');
    }

    public function testDispatchRestoreEvents(): void
    {
        // arrange
        $oldUser = MySqlUser::factory()->create([
            'deleted_at' => Carbon::now()->subDay(),
        ]);
        $newUser = MySqlUser::factory()->make([
            'email' => $oldUser->email,
            'deleted_at' => null,
        ]);
        $restoringSpy = Mockery::spy(Callback::class);
        $restoredSpy = Mockery::spy(Callback::class);
        MySqlUser::restoring($restoringSpy);
        MySqlUser::restored($restoredSpy);
        /** @var BulkUpdate $sut */
        $sut = $this->app->make(BulkUpdate::class);

        // act
        $sut->update(MySqlUser::class, [$newUser], ['email']);

        // assert
        $this->assertDatabaseHas(MySqlUser::table(), [
            'name' => $newUser->name,
            'email' => $newUser->email,
            'deleted_at' => null,
        ], $newUser->getConnectionName());
        $restoringSpy->shouldHaveReceived('__invoke');
        $restoredSpy->shouldHaveReceived('__invoke');
    }

    public function testRunRestoreCallbacks(): void
    {
        // arrange
        $oldUser = MySqlUser::factory()->create([
            'deleted_at' => Carbon::now()->subDay(),
        ]);
        $newUser = MySqlUser::factory()->make([
            'email' => $oldUser->email,
            'deleted_at' => null,
        ]);
        $restoringSpy = Mockery::spy(Callback::class);
        $restoredSpy = Mockery::spy(Callback::class);
        /** @var BulkUpdate $sut */
        $sut = $this->app->make(BulkUpdate::class)
            ->onRestoring($restoringSpy)
            ->onRestored($restoredSpy);

        // act
        $sut->update(MySqlUser::class, [$newUser], ['email']);

        // assert
        $restoringSpy->shouldHaveReceived('__invoke');
        $restoredSpy->shouldHaveReceived('__invoke');
    }

    /**
     * @return string[][]
     */
    public function entities(): array
    {
        return [
            [MySqlEntityWithAutoIncrement::class],
            [MySqlEntityWithoutAutoIncrement::class],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateEntityCollectionTestFeature = $this->app->make(GenerateEntityCollectionTestFeature::class);
        $this->saveAndFillEntityCollectionTestFeature = $this->app->make(SaveAndFillEntityCollectionTestFeature::class);
        $this->setModelEventSpyListenersTestFeature = $this->app->make(SetModelEventSpyListenersTestFeature::class);
        $this->generateSpyListenersTestFeature = $this->app->make(GenerateSpyListenersTestFeature::class);
    }
}
