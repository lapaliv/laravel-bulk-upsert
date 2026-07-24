<?php

namespace Tests\App\Observers;

use Closure;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

/**
 * The observer which the tests register on their models.
 *
 * A test registers the class once with `Model::observe()` and then adds single
 * listeners through {@see listen()}. The listeners are kept in a static list, so they
 * can be replaced or dropped between two tests.
 *
 * Every event method below looks its own listener up by the name of the method and
 * calls it with the arguments of the event. When the event has no listener, the method
 * returns `null`.
 *
 * @internal
 */
final class Observer
{
    /**
     * The registered listeners, keyed by the value of the event.
     *
     * @var array<string, \Closure>
     */
    public static array $listeners = [];

    /**
     * Register a listener for the given event.
     *
     * A listener which is already registered for this event is replaced.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     * @param callable $callback
     *
     * @return void
     */
    public static function listen(BulkEventEnum $event, callable $callback): void
    {
        self::$listeners[$event->value] = Closure::fromCallable($callback);
    }

    /**
     * Register the same listener for every event of the package.
     *
     * @param callable $callback
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum[] $except
     *
     * @return void
     */
    public static function listenAny(callable $callback, array $except = []): void
    {
        foreach (BulkEventEnum::cases() as $event) {
            if (!in_array($event, $except)) {
                self::listen($event, $callback);
            }
        }
    }

    /**
     * Drop every registered listener.
     *
     * @return void
     */
    public static function flush(): void
    {
        self::$listeners = [];
    }

    /**
     * Handle the `creating` event of a single model.
     *
     * @return mixed
     */
    public function creating(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `creatingMany` event of the whole collection.
     *
     * @return mixed
     */
    public function creatingMany(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `created` event of a single model.
     *
     * @return mixed
     */
    public function created(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `createdMany` event of the whole collection.
     *
     * @return mixed
     */
    public function createdMany(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `updating` event of a single model.
     *
     * @return mixed
     */
    public function updating(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `updatingMany` event of the whole collection.
     *
     * @return mixed
     */
    public function updatingMany(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `updated` event of a single model.
     *
     * @return mixed
     */
    public function updated(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `updatedMany` event of the whole collection.
     *
     * @return mixed
     */
    public function updatedMany(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `saving` event of a single model.
     *
     * @return mixed
     */
    public function saving(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `savingMany` event of the whole collection.
     *
     * @return mixed
     */
    public function savingMany(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `saved` event of a single model.
     *
     * @return mixed
     */
    public function saved(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `savedMany` event of the whole collection.
     *
     * @return mixed
     */
    public function savedMany(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `deleting` event of a single model.
     *
     * @return mixed
     */
    public function deleting(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `deletingMany` event of the whole collection.
     *
     * @return mixed
     */
    public function deletingMany(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `deleted` event of a single model.
     *
     * @return mixed
     */
    public function deleted(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `deletedMany` event of the whole collection.
     *
     * @return mixed
     */
    public function deletedMany(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `forceDeleting` event of a single model.
     *
     * @return mixed
     */
    public function forceDeleting(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `forceDeletingMany` event of the whole collection.
     *
     * @return mixed
     */
    public function forceDeletingMany(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `forceDeleted` event of a single model.
     *
     * @return mixed
     */
    public function forceDeleted(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `forceDeletedMany` event of the whole collection.
     *
     * @return mixed
     */
    public function forceDeletedMany(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `restoring` event of a single model.
     *
     * @return mixed
     */
    public function restoring(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `restoringMany` event of the whole collection.
     *
     * @return mixed
     */
    public function restoringMany(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `restored` event of a single model.
     *
     * @return mixed
     */
    public function restored(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    /**
     * Handle the `restoredMany` event of the whole collection.
     *
     * @return mixed
     */
    public function restoredMany(): mixed
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }
}
