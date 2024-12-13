<?php

declare(strict_types=1);

namespace Lapaliv\BulkUpsert\Converters;

use Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported;

class MixedValueToScalarConverter
{
    public function handle(mixed $value): int|float|bool|string
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        } elseif (is_object($value) && PHP_VERSION_ID >= 80100 && enum_exists(get_class($value))) {
            return $value->value;
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            return $value->__toString();
        }

        throw new BulkValueTypeIsNotSupported($value);
    }
}
