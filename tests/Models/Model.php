<?php

namespace Lapaliv\BulkUpsert\Tests\Models;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Builder;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

abstract class Model extends \Illuminate\Database\Eloquent\Model implements BulkModel
{
    public function fireModelEvent($event, $halt = true)
    {
        return parent::fireModelEvent($event, $halt);
    }

    protected static function getSchema(): Builder
    {
        return Manager::schema((new static())->getConnectionName());
    }

    /**
     * Register a model event with the dispatcher.
     *
     * @param string $event
     * @param \Illuminate\Events\QueuedClosure|\Closure|string|array $callback
     * @return void
     */
    public static function registerModelEvent($event, $callback)
    {
        parent::registerModelEvent($event, $callback);
    }
}
