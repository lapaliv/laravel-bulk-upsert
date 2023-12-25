<?php

namespace Lapaliv\BulkUpsert\Tests\Unit;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Collections\BulkRows;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Tests\App\Support\TestCallback;
use Mockery;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;

trait ModelListenerTestTrait
{
    /**
     * Clean up the testing environment before the next test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        Mockery::close();
    }

    protected function makeSimpleModelListener(
        string $event,
        BulkEventDispatcher $eventDispatcher,
    ): LegacyMockInterface|MockInterface {
        $result = Mockery::spy(TestCallback::class);
        $eventDispatcher->listen($event, $result);

        return $result;
    }

    protected function makeModelListenerWithReturningValue(
        string $event,
        BulkEventDispatcher $eventDispatcher,
        mixed $returningValue,
    ): LegacyMockInterface|MockInterface {
        $result = Mockery::spy(TestCallback::class);
        $result->expects('__invoke')
            ->zeroOrMoreTimes()
            ->andReturnValues($returningValue);
        $eventDispatcher->listen($event, $result);

        return $result;
    }

    protected function assertModelListenerArguments(Collection $expectedModels, ...$args): bool
    {
        self::assertCount(1, $args);
        self::assertContainsModel($args[0], $expectedModels);

        return true;
    }

    protected function assertCollectionListenerArguments(Collection $expectedModels, ...$args): bool
    {
        self::assertCount(2, $args);
        self::assertInstanceOf(Collection::class, $args[0]);
        self::assertInstanceOf(BulkRows::class, $args[1]);

        self::assertCount($expectedModels->count(), $args[0]);
        self::assertCount($expectedModels->count(), $args[1]);

        foreach ($expectedModels as $expectedModel) {
            self::assertContainsModel($expectedModel, $args[0]);
            self::assertContainsModel($expectedModel, $args[1]->pluck('model'), 'BulkRows not contains the model');
        }

        return true;
    }

    private static function assertContainsModel(Model $model, iterable $expectedModels, string $message = null): bool
    {
        $modelAttributes = $model->attributesToArray();

        foreach ($expectedModels as $expectedModel) {
            if ($expectedModel === $model) {
                return true;
            }

            $generalAttributes = array_intersect_key($modelAttributes, $expectedModel->attributesToArray());

            foreach ($generalAttributes as $key => $value) {
                if ($modelAttributes[$key] !== $value) {
                    continue 2;
                }
            }

            return true;
        }

        self::fail($message ?? ('Failed asserting that a traversable contains ' . get_class($model)));
    }
}
