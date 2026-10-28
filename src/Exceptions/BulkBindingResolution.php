<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Illuminate\Contracts\Container\BindingResolutionException;
use Lapaliv\BulkUpsert\Contracts\BulkException;

/**
 * Thrown when the container cannot resolve a class which the package needs.
 */
class BulkBindingResolution extends BindingResolutionException implements BulkException
{
    //
}
