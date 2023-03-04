<?php

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Exception;
use Lapaliv\BulkUpsert\Entities\BulkScenarioConfig;
use Lapaliv\BulkUpsert\Features\PrepareUpdateBuilderFeature;
use Lapaliv\BulkUpsert\Support\BulkCallback;
use Lapaliv\BulkUpsert\Tests\App\Collections\EntityCollection;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateEntityCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithAutoIncrement;
use Lapaliv\BulkUpsert\Tests\App\Support\Callback;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;
use Mockery;
use Mockery\VerificationDirector;

final class PrepareUpdateBuilderFeatureTest extends UnitTestCase
{
    private GenerateEntityCollectionTestFeature $generateEntityCollectionTestFeature;

    /**
     * @param BulkCallback|null $updatingCallback
     * @param BulkCallback|null $savingCallback
     * @return void
     * @throws Exception
     * @dataProvider fillInBuilderDataProvider
     */
    public function testWithOneUniqueAttribute(
        ?BulkCallback $updatingCallback,
        ?BulkCallback $savingCallback,
    ): void {
        // arrange
        /** @var PrepareUpdateBuilderFeature $sut */
        $sut = $this->app->make(PrepareUpdateBuilderFeature::class);
        $model = new MySqlEntityWithAutoIncrement();
        $entities = $this->generateEntityCollectionTestFeature->handle($model::class, 4, ['uuid', 'string']);

        // act
        $builder = $sut->handle(
            $model,
            $entities,
            new BulkScenarioConfig(
                uniqueAttributes: ['uuid'],
                updatingCallback: $updatingCallback,
                savingCallback: $savingCallback,
            )
        );

        // assert
        self::assertNotNull($builder);
        self::assertEquals($builder->getTable(), $model->getTable());
        self::assertCount($builder->getLimit(), $entities);

        self::assertCount(3, $builder->getSets());
        self::assertArrayHasKey('string', $builder->getSets());
        self::assertArrayHasKey('created_at', $builder->getSets());
        self::assertArrayHasKey('updated_at', $builder->getSets());
        self::assertArrayNotHasKey('uuid', $builder->getSets());
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testCallbacks(): void
    {
        // arrange
        /** @var PrepareUpdateBuilderFeature $sut */
        $sut = $this->app->make(PrepareUpdateBuilderFeature::class);
        $model = new MySqlEntityWithAutoIncrement();
        $entities = $this->generateEntityCollectionTestFeature->handle($model::class, 4, ['uuid', 'string']);
        $updatingCallback = Mockery::spy(Callback::class);
        $savingCallback = Mockery::spy(Callback::class);

        // act
        $sut->handle(
            $model,
            $entities,
            new BulkScenarioConfig(
                uniqueAttributes: ['uuid'],
                updatingCallback: new BulkCallback($updatingCallback),
                savingCallback: new BulkCallback($savingCallback),
            )
        );

        // assert
        /** @var VerificationDirector $callback */
        $callback = $savingCallback->shouldHaveReceived('__invoke');
        $callback->times(1)
            ->withArgs(
                function (EntityCollection $collection) use ($entities): bool {
                    self::assertCount($entities->count(), $collection);

                    $collection->each(
                        function (MySqlEntityWithAutoIncrement $entity): void {
                            self::assertNotNull($entity->created_at);
                            self::assertNotNull($entity->updated_at);
                        }
                    );

                    return true;
                }
            );

        $callback = $updatingCallback->shouldHaveReceived('__invoke');
        $callback->times(1)
            ->withArgs(
                function (EntityCollection $collection) use ($entities): bool {
                    self::assertCount($entities->count(), $collection);

                    $collection->each(
                        function (MySqlEntityWithAutoIncrement $entity): void {
                            self::assertTrue($entity->isDirty());
                        }
                    );

                    return true;
                }
            );
    }

    /**
     * @param BulkCallback|null $updatingCallback
     * @param BulkCallback|null $savingCallback
     * @return void
     * @throws Exception
     * @dataProvider fillInBuilderDataProvider
     */
    public function testWithSeveralUniqueAttribute(
        ?BulkCallback $updatingCallback,
        ?BulkCallback $savingCallback,
    ): void {
        // arrange
        /** @var PrepareUpdateBuilderFeature $sut */
        $sut = $this->app->make(PrepareUpdateBuilderFeature::class);
        $model = new MySqlEntityWithAutoIncrement();
        $entities = $this->generateEntityCollectionTestFeature->handle($model::class, 4, ['uuid', 'string', 'integer']);

        // act
        $builder = $sut->handle(
            $model,
            $entities,
            new BulkScenarioConfig(
                uniqueAttributes: ['uuid', 'string'],
                updatingCallback: $updatingCallback,
                savingCallback: $savingCallback,
            )
        );

        // assert
        self::assertNotNull($builder);
        self::assertEquals($builder->getTable(), $model->getTable());
        self::assertCount($builder->getLimit(), $entities);

        self::assertCount(3, $builder->getSets());
        self::assertArrayHasKey('integer', $builder->getSets());
        self::assertArrayHasKey('created_at', $builder->getSets());
        self::assertArrayHasKey('updated_at', $builder->getSets());
        self::assertArrayNotHasKey('uuid', $builder->getSets());
        self::assertArrayNotHasKey('string', $builder->getSets());
    }

    /**
     * @return array[]
     */
    public function fillInBuilderDataProvider(): array
    {
        return [
            'without callbacks' => [
                null,
                null,
            ],
            'with callbacks' => [
                new BulkCallback(fn () => null),
                new BulkCallback(fn () => null),
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateEntityCollectionTestFeature = $this->app->make(GenerateEntityCollectionTestFeature::class);
    }
}
