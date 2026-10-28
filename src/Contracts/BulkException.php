<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Throwable;

/**
 * The common interface of all the exceptions which the package throws.
 *
 * Catching it is enough to catch every error of the package.
 */
interface BulkException extends Throwable
{
    //
}
