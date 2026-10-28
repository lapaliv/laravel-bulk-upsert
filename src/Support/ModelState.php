<?php

namespace Lapaliv\BulkUpsert\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Updates the bookkeeping of a model after the package has written it.
 *
 * Eloquent has no public API to declare a subset of values saved: `syncOriginal()` and
 * `syncChanges()` take every current attribute, and `setRawAttributes()` drops all the
 * cast caches, which detaches the cast objects (e.g. `AsArrayObject`) the caller still
 * holds. So the protected `original`, `changes` and `previous` properties are set here
 * directly, in the scope of `Model`, and the other attributes and caches are kept.
 *
 * This is a deliberate dependency on the internals of `HasAttributes`, which are the same
 * in Laravel 11, 12 and 13 (`previous` exists since 12 and is set only when present).
 *
 * @internal
 */
final class ModelState
{
    /**
     * Declare the given storage values written: they become the original ones.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array<string, mixed> $written
     *
     * @return void
     */
    public static function syncWrittenOriginal(Model $model, array $written): void
    {
        self::run($model, /** @psalm-suppress InaccessibleProperty The closure runs in the scope of Model. */ static function (Model $target) use ($written): void {
            /** @var array<string, mixed> $original The closure runs in the scope of Model. */
            $original = $target->original;
            $target->original = array_replace($original, $written);
        });
    }

    /**
     * Remember the changes which the given storage values have made.
     *
     * A value is a change when Eloquent's `originalIsEquivalent()` says it is not equivalent
     * to the original one, so the casts compare the values. The check runs on the model
     * itself, with the written values put into the attributes for a moment and the cast
     * caches emptied; the attributes and the caches are restored right after it, even on
     * an exception. The model is never cloned, so a custom `__clone()` does not run.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array<string, mixed> $written
     *
     * @return void
     */
    public static function syncWrittenChanges(Model $model, array $written): void
    {
        self::run($model, /** @psalm-suppress InaccessibleProperty, MixedAssignment, MixedArgument The closure runs in the scope of Model. */ static function (Model $target) use ($written): void {
            $attributes = $target->attributes;
            $classCastCache = $target->classCastCache;
            $attributeCastCache = $target->attributeCastCache;
            $changes = [];

            try {
                $target->attributes = array_replace($target->getRawOriginal(), $written);
                $target->classCastCache = [];
                $target->attributeCastCache = [];

                foreach (array_keys($written) as $key) {
                    if (!$target->originalIsEquivalent($key)) {
                        $changes[$key] = $written[$key];
                    }
                }
            } finally {
                $target->attributes = $attributes;
                $target->classCastCache = $classCastCache;
                $target->attributeCastCache = $attributeCastCache;
            }

            $target->changes = $changes;

            if (property_exists($target, 'previous')) {
                $original = $target->getRawOriginal();
                /** @psalm-suppress UndefinedPropertyAssignment `previous` exists since Laravel 12. */
                $target->previous = array_intersect_key($original, $changes);
            }
        });
    }

    /**
     * Set a raw attribute and its original value without running a mutator.
     *
     * Only the cast cache of this attribute is dropped, so a cached cast object of the old
     * value is not returned for the new one, while the other cast objects stay bound.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public static function setStored(Model $model, string $key, mixed $value): void
    {
        self::run($model, /** @psalm-suppress InaccessibleProperty, MixedAssignment The closure runs in the scope of Model. */ static function (Model $target) use ($key, $value): void {
            // The closure runs in the scope of Model, so the protected arrays are reachable.
            /** @var array<string, mixed> $attributes */
            $attributes = $target->attributes;
            /** @var array<string, mixed> $original */
            $original = $target->original;
            /** @var array<string, mixed> $classCastCache */
            $classCastCache = $target->classCastCache;
            /** @var array<string, mixed> $attributeCastCache */
            $attributeCastCache = $target->attributeCastCache;

            $attributes[$key] = $value;
            $original[$key] = $value;
            unset($classCastCache[$key], $attributeCastCache[$key]);

            $target->attributes = $attributes;
            $target->original = $original;
            $target->classCastCache = $classCastCache;
            $target->attributeCastCache = $attributeCastCache;
        });
    }

    /**
     * Run the callback in the scope of `Model`, so it may reach the protected properties.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param \Closure(\Illuminate\Database\Eloquent\Model): void $callback
     *
     * @return void
     */
    private static function run(Model $model, Closure $callback): void
    {
        $scoped = Closure::bind($callback, null, Model::class);

        if (!$scoped instanceof Closure) {
            throw new LogicException('The model state cannot be updated in the scope of the model.');
        }

        $scoped($model);
    }
}
