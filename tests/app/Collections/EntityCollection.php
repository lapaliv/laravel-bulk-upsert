<?php

namespace Lapaliv\BulkUpsert\Tests\App\Collections;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Tests\App\Models\Entity;

/**
 * @method Entity|null first(callable $callback = null, $default = null)
 * @method Entity|null last(callable $callback = null, $default = null)
 */
class EntityCollection extends Collection
{
    // Nothing
}
