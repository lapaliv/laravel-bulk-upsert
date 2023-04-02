<?php

namespace Lapaliv\BulkUpsert\Tests\App\Factories;

use Lapaliv\BulkUpsert\Tests\App\Models\MySqlUser;

/**
 * @internal
 */
class MySqlUserFactory extends UserFactory
{
    protected $model = MySqlUser::class;
}
