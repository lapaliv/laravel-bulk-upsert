<?php

namespace Tests\Unit;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Collections\BulkRowCollection;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Tests\App\Observers\Observer;
use Tests\App\Support\TestCallback;
use Mockery;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;

/**
 * Black-box helpers for asserting model/collection lifecycle events.
 *
 * Listeners are attached through the public {@see \Tests\App\Observers\Observer} (the same way a
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
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     *
     * @return \Mockery\LegacyMockInterface|\Mockery\MockInterface
     */
    protected function listenEvent(BulkEventEnum $event): LegacyMockInterface|MockInterface
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
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     * @param mixed $returningValues
     *
     * @return \Mockery\LegacyMockInterface|\Mockery\MockInterface
     */
    protected function listenEventReturning(BulkEventEnum $event, mixed $returningValues): LegacyMockInterface|MockInterface
    {
        $spy = Mockery::spy(TestCallback::class);
        $spy->expects('__invoke')
            ->zeroOrMoreTimes()
            ->andReturnValues($returningValues);
        Observer::listen($event, $spy);

        return $spy;
    }

    /**
     * Assert that a listener of a single model got the expected arguments.
     *
     * The listener has to get exactly one argument, and this model has to be one of the
     * expected ones. The method is used as an argument matcher of a spy, so it gives
     * `true` back when every assertion holds.
     *
     * @param \Illuminate\Database\Eloquent\Collection $expectedModels
     * @param mixed ...$args
     *
     * @return bool
     */
    protected function assertModelListenerArguments(Collection $expectedModels, ...$args): bool
    {
        self::assertCount(1, $args);
        self::assertContainsModel($args[0], $expectedModels);

        return true;
    }

    /**
     * Assert that a listener of a collection got the expected arguments.
     *
     * The listener has to get two arguments: the collection of the models and the bulk
     * rows. Both have to hold every expected model and nothing more. The method is used
     * as an argument matcher of a spy, so it gives `true` back when every assertion
     * holds.
     *
     * @param \Illuminate\Database\Eloquent\Collection $expectedModels
     * @param mixed ...$args
     *
     * @return bool
     */
    protected function assertCollectionListenerArguments(Collection $expectedModels, ...$args): bool
    {
        self::assertCount(2, $args);
        self::assertInstanceOf(Collection::class, $args[0]);
        self::assertInstanceOf(BulkRowCollection::class, $args[1]);

        self::assertCount($expectedModels->count(), $args[0]);
        self::assertCount($expectedModels->count(), $args[1]);

        foreach ($expectedModels as $expectedModel) {
            self::assertContainsModel($expectedModel, $args[0]);
            self::assertContainsModel($expectedModel, $args[1]->map(fn($row) => $row->getModel()), 'BulkRowCollection not contains the model');
        }

        return true;
    }

    /**
     * Assert that the given models hold the model.
     *
     * A model counts as found when it is the same object, or when all attributes which
     * both models have are equal. The method stops the test when nothing matches.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param iterable $expectedModels
     * @param string|null $message
     *
     * @return bool
     */
    private static function assertContainsModel(Model $model, iterable $expectedModels, ?string $message = null): bool
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
