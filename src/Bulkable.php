<?php

namespace Lapaliv\BulkUpsert;

use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

/**
 * Gives an Eloquent model the bulk features of the package.
 *
 * The trait replaces the builder of the model and registers the events which are
 * dispatched for a whole collection.
 *
 * @template TCollection of \Illuminate\Database\Eloquent\Collection
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
     * @return \Lapaliv\BulkUpsert\BulkBuilder<TCollection, $this>
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
        static::registerModelEvent(BulkEventEnum::CreatingMany->value, $callback);
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
        static::registerModelEvent(BulkEventEnum::CreatedMany->value, $callback);
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
        static::registerModelEvent(BulkEventEnum::UpdatingMany->value, $callback);
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
        static::registerModelEvent(BulkEventEnum::UpdatedMany->value, $callback);
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
        static::registerModelEvent(BulkEventEnum::SavingMany->value, $callback);
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
        static::registerModelEvent(BulkEventEnum::SavedMany->value, $callback);
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
        static::registerModelEvent(BulkEventEnum::DeletingMany->value, $callback);
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
        static::registerModelEvent(BulkEventEnum::DeletedMany->value, $callback);
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
        static::registerModelEvent(BulkEventEnum::RestoringMany->value, $callback);
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
        static::registerModelEvent(BulkEventEnum::RestoredMany->value, $callback);
    }

    /**
     * Initialize the trait for the model.
     *
     * The collection events become the observable events of the model, so the
     * observers of the model can listen to them.
     *
     * @return void
     */
    public function initializeBulkable(): void
    {
        $this->setObservableEvents(
            array_map(
                fn(BulkEventEnum $enum): string => $enum->value,
                BulkEventEnum::collection()
            )
        );
    }
}
