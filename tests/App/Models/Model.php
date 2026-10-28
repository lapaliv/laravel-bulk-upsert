<?php

namespace Tests\App\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Bulkable;

/**
 * The base class of every test model of the suite.
 *
 * It gives the models the bulk features of the package and a few small helpers which
 * the tests need to build and drop their tables.
 *
 * @internal
 */
abstract class Model extends Eloquent
{
    use Bulkable;

    /**
     * Get the name of the table of the model.
     *
     * The model is resolved through the container, so the method can be called in a
     * static way from a test.
     *
     * @return string
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public static function table(): string
    {
        return Container::getInstance()->make(static::class)->getTable();
    }

    /**
     * Register a model event with the dispatcher.
     *
     * The method only calls the parent one. It exists to make the event registration
     * public, so a test can add a listener from the outside.
     *
     * @param string $event
     * @param callable|string $callback
     *
     * @return void
     */
    public static function registerModelEvent($event, $callback): void
    {
        parent::registerModelEvent($event, $callback);
    }

    /**
     * Drop the table of the model when it exists.
     *
     * @return void
     */
    public static function dropTable(): void
    {
        self::getSchema()->dropIfExists(self::table());
    }

    /**
     * Get the schema builder of the default connection.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    protected static function getSchema(): Builder
    {
        return DB::getSchemaBuilder();
    }
}
