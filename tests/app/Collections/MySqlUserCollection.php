<?php

namespace Lapaliv\BulkUpsert\Tests\App\Collections;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;

/**
 * @method MySqlUserCollection|null first(callable $callback = null, $default = null)
 * @method MySqlUserCollection|null last(callable $callback = null, $default = null)
 * @extends Collection<scalar, MySqlUser>
 */
class MySqlUserCollection extends Collection
{
    // Nothing
}
