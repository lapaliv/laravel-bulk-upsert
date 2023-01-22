<?php

namespace Lapaliv\BulkUpsert\Tests\App\Collections;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;

/**
 * @method PostgreSqlUser|MySqlUser|null get($key, $default = null)
 * @method PostgreSqlUser|MySqlUser|null first(callable $callback = null, $default = null)
 */
class UserCollection extends Collection
{
    // Nothing
}
