<?php

namespace Lapaliv\BulkUpsert\Tests\App\Collection;

use ArrayIterator;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Tests\App\Models\Article;

/**
 * @internal
 *
 * @method ArrayIterator|Article[] getIterator()
 * @method Article|null first(callable $callback = null, $default = null)
 * @method Article|null last(callable $callback = null, $default = null)
 * @method Article|null get($key, $default = null)
 */
final class ArticleCollection extends Collection
{
    //
}
