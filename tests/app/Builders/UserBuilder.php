<?php

namespace Lapaliv\BulkUpsert\Tests\App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\BulkBuilderTrait;
use Lapaliv\BulkUpsert\Tests\App\Models\User;

/**
 * @internal
 *
 * @method User firstOrFail($columns = ['*'])
 * @method UserBuilder onlyTrashed()
 * @method UserBuilder withTrashed()
 */
final class UserBuilder extends Builder
{
    use BulkBuilderTrait;
}
