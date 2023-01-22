<?php

namespace Lapaliv\BulkUpsert\Tests\Feature;

use Carbon\Carbon;
use Exception;
use Lapaliv\BulkUpsert\BulkInsert;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Tests\App\Collections\EntityCollection;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateEntityCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateSpyListenersTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\SaveAndFillEntityCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\SetModelEventSpyListenersTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\Entity;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithAutoIncrement;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithoutAutoIncrement;
use Lapaliv\BulkUpsert\Tests\App\Support\Callback;
use Lapaliv\BulkUpsert\Tests\App\Traits\CheckEntityInDatabase;
use Lapaliv\BulkUpsert\Tests\TestCase;
use Mockery;
use Mockery\VerificationDirector;

final class BulkInsertTest extends TestCase
{
    use CheckEntityInDatabase;

    private GenerateEntityCollectionTestFeature $generateEntityCollectionTestFeature;
    private SetModelEventSpyListenersTestFeature $setModelEventSpyListenersTestFeature;
    private GenerateSpyListenersTestFeature $generateSpyListenersTestFeature;
    private SaveAndFillEntityCollectionTestFeature $saveAndFillEntityCollectionTestFeature;

    /**
     * @param class-string<Entity> $model
     * @return void
     * @throws Exception
     * @dataProvider entities
     */
    public function testSuccessful(string $model): void
    {
        // arrange
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 3);
        /** @var BulkInsert $sut */
        $sut = $this->app->make(BulkInsert::class);

        // act
        $sut->insert($model, ['uuid'], $entities);

        // assert
        $entities->each(
            fn (Entity $entity) => $this->assertDatabaseHasEntity($entity)
        );
    }

    /**
     * @param class-string<Entity> $model
     * @return void
     * @throws Exception
     * @dataProvider entities
     */
    public function testTimestamps(string $model): void
    {
        // arrange
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 3);
        /** @var BulkInsert $sut */
        $sut = $this->app->make(BulkInsert::class);

        // act
        $sut->insert($model, ['uuid'], $entities);

        // assert
        $entities->each(
            function (Entity $entity) use ($model): void {
                $hasEntity = $model::query()
                    ->where('uuid', $entity->uuid)
                    ->whereNotNull($entity->getCreatedAtColumn())
                    ->whereNotNull($entity->getUpdatedAtColumn())
                    ->exists();

                self::assertTrue($hasEntity);
            }
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
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 3);
        /** @var BulkInsert $sut */
        $sut = $this->app->make(BulkInsert::class);
        $listeners = $this->generateSpyListenersTestFeature->handle();
        $this->setModelEventSpyListenersTestFeature->handle($model, $listeners);

        // act
        $sut->insert($model, ['uuid'], $entities);

        // assert
        foreach ([BulkEventEnum::CREATING, BulkEventEnum::SAVING] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->times($entities->count())
                ->withArgs(
                    function (Entity $entity): bool {
                        self::assertFalse($entity->wasRecentlyCreated);
                        self::assertFalse($entity->exists);

                        return true;
                    }
                );
        }

        foreach ([BulkEventEnum::UPDATING, BulkEventEnum::UPDATED] as $event) {
            $listeners[$event]->shouldNotHaveReceived('__invoke');
        }

        foreach ([BulkEventEnum::CREATED, BulkEventEnum::SAVED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->times($entities->count())
                ->withArgs(
                    function (Entity $entity): bool {
                        self::assertTrue($entity->wasRecentlyCreated);
                        self::assertTrue($entity->exists);

                        return true;
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
    public function testCallbacks(string $model): void
    {
        // arrange
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 4);
        $this->saveAndFillEntityCollectionTestFeature->handle($entities, 1, [
            'created_at' => Carbon::now()->subMinute(),
        ]);
        $listeners = $this->generateSpyListenersTestFeature->handle();
        /** @var BulkInsert $sut */
        $sut = $this->app->make(BulkInsert::class);
        $sut->onCreating($listeners[BulkEventEnum::CREATING])
            ->onCreated($listeners[BulkEventEnum::CREATED])
            ->onSaved($listeners[BulkEventEnum::SAVED]);

        // act
        $sut->insertOrIgnore($model, ['uuid'], $entities);

        // assert
        foreach ([BulkEventEnum::CREATING, BulkEventEnum::SAVED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->once()
                ->withArgs(
                    function (EntityCollection $collection) use ($entities): bool {
                        return $collection->count() === $entities->count();
                    }
                );
        }

        foreach ([BulkEventEnum::UPDATING, BulkEventEnum::UPDATED, BulkEventEnum::SAVING] as $event) {
            $listeners[$event]->shouldNotHaveReceived('__invoke');
        }

        /** @var VerificationDirector $callback */
        $callback = $listeners[BulkEventEnum::CREATED]->shouldHaveReceived('__invoke');
        $callback->once()
            ->withArgs(
                function (EntityCollection $collection) use ($entities): bool {
                    return $collection->count() === $entities->count() - 1;
                }
            );
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
        $total = 11;
        $entities = $this->generateEntityCollectionTestFeature->handle($model, $total);
        $callback = Mockery::spy(Callback::class);
        /** @var BulkInsert $sut */
        $sut = $this->app->make(BulkInsert::class);
        $sut->chunk($chunkSize, $callback);

        // act
        $sut->insert($model, ['uuid'], $entities);

        // assert
        /** @var VerificationDirector $spy */
        $spy = $callback->shouldHaveReceived('__invoke');
        $spy->times((int)ceil($total / 2))
            ->withArgs(
                function (EntityCollection $chunk) use ($chunkSize): bool {
                    return $chunk->count() <= $chunkSize;
                }
            );
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
        $this->setModelEventSpyListenersTestFeature = $this->app->make(SetModelEventSpyListenersTestFeature::class);
        $this->generateSpyListenersTestFeature = $this->app->make(GenerateSpyListenersTestFeature::class);
        $this->saveAndFillEntityCollectionTestFeature = $this->app->make(SaveAndFillEntityCollectionTestFeature::class);
    }
}
