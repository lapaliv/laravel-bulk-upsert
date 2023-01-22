<?php

namespace Lapaliv\BulkUpsert\Tests\App\Models;

use Closure;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Builder;
use Illuminate\Events\QueuedClosure;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

abstract class Model extends \Illuminate\Database\Eloquent\Model implements BulkModel
{
    final public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * Register a model event with the dispatcher.
     *
     * @param string $event
     * @param QueuedClosure|Closure|string|array $callback
     * @return void
     */
    public static function registerModelEvent($event, $callback): void
    {
        parent::registerModelEvent($event, $callback);
    }

    protected static function getSchema(): Builder
    {
        return Manager::schema((new static())->getConnectionName());
    }

    public function fireModelEvent($event, $halt = true)
    {
        return parent::fireModelEvent($event, $halt);
    }
}
