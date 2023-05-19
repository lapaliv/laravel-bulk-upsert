<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Tests\App\Collection\UserCollection;
use Lapaliv\BulkUpsert\Tests\App\Models\PostgreSqlUser;

/**
 * @internal
 *
 * @method PostgreSqlUser|UserCollection create($attributes = [], ?Model $parent = null)
 * @method PostgreSqlUser|UserCollection make($attributes = [], ?Model $parent = null)
 * @method PostgreSqlUser|UserCollection createMany(iterable $records)
 */
final class PostgreSqlUserFactory extends UserFactory
{
    protected $model = PostgreSqlUser::class;
}
