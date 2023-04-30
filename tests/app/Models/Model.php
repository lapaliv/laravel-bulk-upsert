<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Schema\Builder;
use Lapaliv\BulkUpsert\Bulkable;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

/**
 * @internal
 */
abstract class Model extends Eloquent implements BulkModel
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
        return Manager::schema(
            Container::getInstance()->make(static::class)->getConnectionName()
        );
    }
}
