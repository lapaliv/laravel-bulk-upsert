<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Illuminate\Contracts\Container\BindingResolutionException;
use Lapaliv\BulkUpsert\Contracts\BulkException;

class BulkBindingResolution extends BindingResolutionException implements BulkException
{
    //
}
