<?php

namespace Lapaliv\BulkUpsert\Tests\Unit\Features;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Features\FillWasRecentlyCreatedFeature;
use Lapaliv\BulkUpsert\Tests\App\Features\GenerateEntityCollectionTestFeature;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlEntityWithAutoIncrement;
use Lapaliv\BulkUpsert\Tests\UnitTestCase;

final class FillWasRecentlyCreatedFeatureTest extends UnitTestCase
{
    private GenerateEntityCollectionTestFeature $generateEntityCollectionTestFeature;

    /**
     * @return void
     * @throws Exception
     */
    public function testByPrimary(): void
    {
        // arrange
        $entities = $this->generateEntityCollectionTestFeature
            ->handle(MySqlEntityWithAutoIncrement::class, 5, ['email'])
            ->each(
                fn (MySqlEntityWithAutoIncrement $entity, int $key) => $entity->id = $key
            );

        /** @var FillWasRecentlyCreatedFeature $sut */
        $sut = $this->app->make(FillWasRecentlyCreatedFeature::class);

        // act
        $sut->handle(new MySqlEntityWithAutoIncrement(), $entities, [], 3, Carbon::now());

        // assert
        $entities->each(
            function (MySqlEntityWithAutoIncrement $entity) {
                self::assertEquals($entity->id > 3, $entity->wasRecentlyCreated);
            }
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testByCreatedAt(): void
    {
        // arrange
        Carbon::setTestNow(Carbon::now()->startOfMinute());

        $entities = new Collection([
            // entities with created_at in the past
            ...$this->generateEntityCollectionTestFeature
                ->handle(MySqlEntityWithAutoIncrement::class, 2, ['uuid'])
                ->each(
                    fn (MySqlEntityWithAutoIncrement $entity, int $key) => $entity->created_at = Carbon::now()->subDay(),
                ),
            // entities with created_at equals now
            ...$this->generateEntityCollectionTestFeature
                ->handle(MySqlEntityWithAutoIncrement::class, 2, ['uuid'])
                ->each(
                    fn (MySqlEntityWithAutoIncrement $entity, int $key) => $entity->created_at = Carbon::now(),
                ),
            // entities with created_at in the future
            ...$this->generateEntityCollectionTestFeature
                ->handle(MySqlEntityWithAutoIncrement::class, 2, ['uuid'])
                ->each(
                    fn (MySqlEntityWithAutoIncrement $entity, int $key) => $entity->created_at = Carbon::now()->addDay(),
                ),
        ]);

        /** @var FillWasRecentlyCreatedFeature $sut */
        $sut = $this->app->make(FillWasRecentlyCreatedFeature::class);

        // act
        $sut->handle(new MySqlEntityWithAutoIncrement(), $entities, [], null, Carbon::now());

        // assert
        $entities->each(
            function (MySqlEntityWithAutoIncrement $entity) {
                self::assertEquals(
                    $entity->created_at->gte(Carbon::now()),
                    $entity->wasRecentlyCreated
                );
            }
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generateEntityCollectionTestFeature = $this->app->make(GenerateEntityCollectionTestFeature::class);
    }
}
