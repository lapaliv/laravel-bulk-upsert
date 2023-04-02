<?php

namespace Lapaliv\BulkUpsert;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Events\QueuedClosure;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;

/**
 * @method static BulkBuilder query()
 */
trait Bulkable
{
    public function newEloquentBuilder($query): BulkBuilder
    {
        return new BulkBuilder($query);
    }

    /**
     * Unset the event dispatcher for models.
     *
     * @return void
     */
    public static function unsetEventDispatcher(): void
    {
        parent::unsetEventDispatcher();
        BulkEventDispatcher::flush();
    }

    /**
     * Register a creatingMany model event with the dispatcher.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function creatingMany(callable $callback): void
    {
        static::registerModelEvent(BulkEventEnum::CREATING_MANY, $callback);
    }

    /**
     * Register a createdMany model event with the dispatcher.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function createdMany(callable $callback): void
    {
        static::registerModelEvent(BulkEventEnum::CREATED_MANY, $callback);
    }

    /**
     * Register a updatingMany model event with the dispatcher.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function updatingMany(callable $callback): void
    {
        static::registerModelEvent(BulkEventEnum::UPDATING_MANY, $callback);
    }

    /**
     * Register a updatedMany model event with the dispatcher.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function updatedMany(callable $callback): void
    {
        static::registerModelEvent(BulkEventEnum::UPDATED_MANY, $callback);
    }

    /**
     * Register a savingMany model event with the dispatcher.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function savingMany(callable $callback): void
    {
        static::registerModelEvent(BulkEventEnum::SAVING_MANY, $callback);
    }

    /**
     * Register a savedMany model event with the dispatcher.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function savedMany(callable $callback): void
    {
        static::registerModelEvent(BulkEventEnum::SAVED_MANY, $callback);
    }

    /**
     * Register a deletingMany model event with the dispatcher.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function deletingMany(callable $callback): void
    {
        static::registerModelEvent(BulkEventEnum::DELETING_MANY, $callback);
    }

    /**
     * Register a deletedMany model event with the dispatcher.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function deletedMany(callable $callback): void
    {
        static::registerModelEvent(BulkEventEnum::DELETED_MANY, $callback);
    }

    /**
     * Register a restoringMany model event with the dispatcher.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function restoringMany(callable $callback): void
    {
        static::registerModelEvent(BulkEventEnum::RESTORING_MANY, $callback);
    }

    /**
     * Register a restoredMany model event with the dispatcher.
     *
     * @param callable $callback
     *
     * @return void
     */
    public static function restoredMany(callable $callback): void
    {
        static::registerModelEvent(BulkEventEnum::RESTORED_MANY, $callback);
    }

    /**
     * Register a model event with the dispatcher.
     *
     * @param string $event
     * @param array|Closure|QueuedClosure|string $callback
     *
     * @return void
     */
    protected static function registerModelEvent($event, $callback)
    {
        if (in_array($event, BulkEventEnum::collection()) === false) {
            parent::registerModelEvent($event, $callback);
        }

        BulkEventDispatcher::registerListener(static::class, $event, $callback);
    }

    /**
     * Register a single observer with the model.
     *
     * @param object|string $class
     *
     * @return void
     *
     * @throws BindingResolutionException
     */
    protected function registerObserver($class)
    {
        parent::registerObserver($class);

        $class = is_object($class) ? $class : Container::getInstance()->make($class);

        if (!is_object($class)) {
            return;
        }

        $className = get_class($class);

        if (class_exists($className) === false) {
            return;
        }

        foreach (BulkEventEnum::collection() as $event) {
            if (method_exists($class, $event)) {
                BulkEventDispatcher::registerListener(static::class, $event, [$class, $event]);
            }
        }
    }
}
