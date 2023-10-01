<?php

namespace Lapaliv\BulkUpsert\Tests\App\Observers;

use Closure;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;

/**
 * @internal
 */
final class Observer
{
    public static array $listeners = [];

    public static function listen(string $event, callable $callback): void
    {
        self::$listeners[$event] = Closure::fromCallable($callback);
    }

    public static function listenAny(callable $callback, array $except = []): void
    {
        foreach (BulkEventEnum::cases() as $event) {
            if (!in_array($event, $except)) {
                self::listen($event, $callback);
            }
        }
    }

    public static function flush(): void
    {
        self::$listeners = [];
    }

    public function creating()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function creatingMany()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function created()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function createdMany()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function updating()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function updatingMany()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function updated()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function updatedMany()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function saving()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function savingMany()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function saved()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function savedMany()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function deleting()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function deletingMany()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function deleted()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function deletedMany()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function forceDeleting()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function forceDeletingMany()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function forceDeleted()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function forceDeletedMany()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function restoring()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function restoringMany()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function restored()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }

    public function restoredMany()
    {
        if (array_key_exists(__FUNCTION__, self::$listeners)) {
            return self::$listeners[__FUNCTION__](...func_get_args());
        }

        return null;
    }
}
