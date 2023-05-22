<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

/**
 * @template TCollection of Collection
 *
 * @method static BulkBuilder|Builder query()
 * @method static Bulk bulk()
 */
trait Bulkable
{
    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     *
     * @return BulkBuilder<TCollection, $this>
     */
    public function newEloquentBuilder($query)
    {
        return new BulkBuilder($query);
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

    public function initializeBulkable(): void
    {
        $this->setObservableEvents(BulkEventEnum::collection());
    }
}
