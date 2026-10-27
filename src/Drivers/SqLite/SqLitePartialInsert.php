<?php

namespace Lapaliv\BulkUpsert\Drivers\SqLite;

use RuntimeException;

/**
 * Undoes an insert attempt which has created fewer rows than it was given.
 *
 * It never leaves the driver: it only makes the transaction of the attempt roll back.
 *
 * @internal
 */
final class SqLitePartialInsert extends RuntimeException
{
    //
}
