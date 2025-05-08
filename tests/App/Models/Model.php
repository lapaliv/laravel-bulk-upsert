<?php

namespace Tests\App\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Bulkable;
use Throwable;

/**
 * @internal
 */
abstract class Model extends Eloquent
{
    use Bulkable;

    public static function table(): string
    {
        return Container::getInstance()->make(static::class)->getTable();
    }

    public static function registerModelEvent($event, $callback): void
    {
        parent::registerModelEvent($event, $callback);
    }

    public static function dropTable(): void
    {
        self::getSchema()->dropIfExists(self::table());
    }

    protected static function getSchema(): Builder
    {
        return DB::getSchemaBuilder();
    }
}
