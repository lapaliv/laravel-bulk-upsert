<?php

namespace Tests\Unit;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Collections\BulkRows;
use Tests\App\Observers\Observer;
use Tests\App\Support\TestCallback;
use Mockery;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;

/**
 * Black-box helpers for asserting model/collection lifecycle events.
 *
 * Listeners are attached through the public {@see Observer} (the same way a
 * consumer of the package would register an observer via `Model::observe()`),
 * so the tests never reach into the package's internal event dispatcher.
 */
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

    /**
     * Register a spying listener for the given event on the observer.
     */
    protected function listenEvent(string $event): LegacyMockInterface|MockInterface
    {
        $spy = Mockery::spy(TestCallback::class);
        Observer::listen($event, $spy);

        return $spy;
    }

    /**
     * Register a listener that returns the given sequence of values.
     *
     * Handy for emulating a listener that cancels the operation by returning
     * `false`, so we can observe how the following events behave.
     */
    protected function listenEventReturning(string $event, mixed $returningValues): LegacyMockInterface|MockInterface
    {
        $spy = Mockery::spy(TestCallback::class);
        $spy->expects('__invoke')
            ->zeroOrMoreTimes()
            ->andReturnValues($returningValues);
        Observer::listen($event, $spy);

        return $spy;
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
