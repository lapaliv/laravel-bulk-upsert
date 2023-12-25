<?php

namespace Lapaliv\BulkUpsert\Tests\App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\BulkBuilderTrait;
use Lapaliv\BulkUpsert\Tests\App\Models\Article;

/**
 * @internal
 *
 * @method Article firstOrFail($columns = ['*'])
 * @method ArticleBuilder onlyTrashed()
 * @method ArticleBuilder withTrashed()
 */
final class ArticleBuilder extends Builder
{
    use BulkBuilderTrait;
}
