<?php

namespace Lapaliv\BulkUpsert\Tests\Feature;

use Exception;
use Lapaliv\BulkUpsert\BulkUpsert;
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
use Lapaliv\BulkUpsert\Tests\FeatureTestCase;
use Mockery;
use Mockery\VerificationDirector;

final class BulkUpsertTest extends FeatureTestCase
{
    use CheckEntityInDatabase;

    private SetModelEventSpyListenersTestFeature $setModelEventSpyListenersTestFeature;
    private GenerateSpyListenersTestFeature $generateSpyListenersTestFeature;
    private GenerateEntityCollectionTestFeature $generateEntityCollectionTestFeature;
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
        /** @var BulkUpsert $sut */
        $sut = $this->app->make(BulkUpsert::class);
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 4);
        $this->saveAndFillEntityCollectionTestFeature->handle($entities, 2);

        // act
        $sut->upsert($model, $entities, ['uuid']);

        // assert
        $entities->each(
            function (Entity $entity) {
                $this->assertDatabaseHasEntity($entity);

                $this->assertDatabaseMissing(
                    $entity->getTable(),
                    [
                        'uuid' => $entity->uuid,
                        'created_at' => null,
                        'updated_at' => null,
                    ],
                    $entity->getConnectionName(),
                );
            }
        );
    }

    /**
     * @param class-string<Entity> $model
     * @return void
     * @throws Exception
     * @dataProvider entities
     */
    public function testWithoutInserting(string $model): void
    {
        // arrange
        /** @var BulkUpsert $sut */
        $sut = $this->app->make(BulkUpsert::class);
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 4);
        $this->saveAndFillEntityCollectionTestFeature->handle($entities, 4);

        // act
        $sut->upsert($model, $entities, ['uuid']);

        // assert
        $entities->each(
            function (Entity $entity) {
                $this->assertDatabaseHasEntity($entity);
            }
        );
    }

    /**
     * @param class-string<Entity> $model
     * @return void
     * @throws Exception
     * @dataProvider entities
     */
    public function testWithoutUpdating(string $model): void
    {
        // arrange
        /** @var BulkUpsert $sut */
        $sut = $this->app->make(BulkUpsert::class);
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 4);

        // act
        $sut->upsert($model, $entities, ['uuid']);

        // assert
        $entities->each(
            function (Entity $entity) {
                $this->assertDatabaseHasEntity($entity);
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
        /** @var BulkUpsert $sut */
        $sut = $this->app->make(BulkUpsert::class);
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 4);
        $this->saveAndFillEntityCollectionTestFeature->handle($entities, 2);
        $listeners = $this->generateSpyListenersTestFeature->handle();
        $this->setModelEventSpyListenersTestFeature->handle($model, $listeners);

        // act
        $sut->upsert($model, $entities, ['uuid']);

        // assert
        foreach ([BulkEventEnum::CREATING, BulkEventEnum::CREATED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->times(2)
                ->withArgs(
                    function (Entity $entity) use ($entities): bool {
                        return $entities->slice(2)
                            ->where('uuid', $entity->uuid)
                            ->isNotEmpty();
                    }
                );
        }

        foreach ([BulkEventEnum::UPDATING, BulkEventEnum::UPDATED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->times(2)
                ->withArgs(
                    function (Entity $entity) use ($entities): bool {
                        return $entities->slice(0, 2)
                            ->where('uuid', $entity->uuid)
                            ->isNotEmpty();
                    }
                );
        }

        foreach ([BulkEventEnum::SAVING, BulkEventEnum::SAVED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->times($entities->count())
                ->withAnyArgs();
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
        $this->saveAndFillEntityCollectionTestFeature->handle($entities, 2);
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
        $sut->upsert($model, $entities, ['uuid']);

        // assert
        foreach ([BulkEventEnum::CREATING, BulkEventEnum::CREATED] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->once()
                ->withArgs(
                    function (EntityCollection $collection) use ($entities): bool {
                        if ($collection->count() !== 2) {
                            return false;
                        }

                        $expectedEmails = $entities->slice(2)
                            ->pluck('uuid')
                            ->sort()
                            ->join(',');
                        $actualEmails = $collection->pluck('uuid')
                            ->sort()
                            ->join(',');

                        return $expectedEmails === $actualEmails;
                    }
                );
        }

        foreach ([BulkEventEnum::UPDATING, BulkEventEnum::UPDATED, BulkEventEnum::SAVING] as $event) {
            /** @var VerificationDirector $callback */
            $callback = $listeners[$event]->shouldHaveReceived('__invoke');
            $callback->once()
                ->withArgs(
                    function (EntityCollection $collection) use ($entities): bool {
                        if ($collection->count() !== 2) {
                            return false;
                        }

                        $expectedEmails = $entities->slice(0, 2)
                            ->pluck('uuid')
                            ->sort()
                            ->join(',');
                        $actualEmails = $collection->pluck('uuid')
                            ->sort()
                            ->join(',');

                        return $expectedEmails === $actualEmails;
                    }
                );
        }

        /** @var VerificationDirector $callback */
        $callback = $listeners[BulkEventEnum::SAVED]->shouldHaveReceived('__invoke');
        $callback->twice()
            ->withArgs(
                function (EntityCollection $collection): bool {
                    return $collection->count() === 2;
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
        $entities = $this->generateEntityCollectionTestFeature->handle($model, 11);
        $this->saveAndFillEntityCollectionTestFeature->handle($entities, 5);
        $callback = Mockery::spy(Callback::class);
        /** @var BulkUpsert $sut */
        $sut = $this->app->make(BulkUpsert::class);
        $sut->chunk($chunkSize, $callback);

        // act
        $sut->upsert($model, $entities, ['uuid']);

        // assert
        /** @var VerificationDirector $spy */
        $spy = $callback->shouldHaveReceived('__invoke');
        $spy->times(6)
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
        $this->saveAndFillEntityCollectionTestFeature->handle($entities, 1);
        /** @var BulkUpsert $sut */
        $sut = $this->app->make(BulkUpsert::class);

        // act
        $sut->upsert($model, $entities, ['uuid'], ['string']);

        // assert
        $this->assertDatabaseHasEntity($entities->first(), only: ['id', 'uuid', 'string']);
        $this->assertDatabaseMissingEntity($entities->first(), except: ['string']);
        $this->assertDatabaseHasEntity($entities->last());
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

        $this->setModelEventSpyListenersTestFeature = $this->app->make(SetModelEventSpyListenersTestFeature::class);
        $this->generateSpyListenersTestFeature = $this->app->make(GenerateSpyListenersTestFeature::class);
        $this->generateEntityCollectionTestFeature = $this->app->make(GenerateEntityCollectionTestFeature::class);
        $this->saveAndFillEntityCollectionTestFeature = $this->app->make(SaveAndFillEntityCollectionTestFeature::class);
    }
}
