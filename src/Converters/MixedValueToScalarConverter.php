<?php

declare(strict_types=1);

namespace Lapaliv\BulkUpsert\Converters;

use BackedEnum;
use Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported;
use Stringable;

/**
 * Converts a value of any type into a scalar value or `null`.
 */
readonly class MixedValueToScalarConverter
{
    /**
     * Convert the given value into a scalar value.
     *
     * A scalar value and `null` are returned as they are. A backed enum gives its
     * value and an object with `__toString()` gives its string.
     *
     * @param mixed $value
     *
     * @return bool|float|int|string|null
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported
     */
    public function handle(mixed $value): int|float|bool|string|null
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        } elseif ($value instanceof BackedEnum) {
            return $value->value;
        } elseif ($value instanceof Stringable) {
            return $value->__toString();
        }

        throw new BulkValueTypeIsNotSupported($value);
    }
}
