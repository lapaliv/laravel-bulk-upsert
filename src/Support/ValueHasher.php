<?php

namespace Lapaliv\BulkUpsert\Support;

use Lapaliv\BulkUpsert\Converters\MixedValueToScalarConverter;

/**
 * Makes an array key from a value of any type.
 *
 * The key is the whole encoded value, not a hash of it. PHP arrays accept strings of
 * any length as keys and resolve their own hash collisions by comparing the whole
 * strings, so two different values never get the same key. A checksum such as
 * `crc32c` would merge different values whose sums happen to collide.
 *
 * @internal
 */
readonly class ValueHasher
{
    /**
     * Create a new hasher instance.
     *
     * @param \Lapaliv\BulkUpsert\Converters\MixedValueToScalarConverter $mixedValueToScalarConverter
     *
     * @return void
     */
    public function __construct(private MixedValueToScalarConverter $mixedValueToScalarConverter)
    {
        //
    }

    /**
     * Make the array key of the given value.
     *
     * The value is turned into a scalar first, so an enum and its backing value give the
     * same key. `serialize()` keeps the type and the length of the scalar, so `null`,
     * `''`, `false`, `0` and `'0'` stay different, the encoding never looks like an
     * integer (PHP would cast such an array key), and a binary string is kept as it is.
     *
     * @param mixed $value
     *
     * @return string
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported
     */
    public function handle(mixed $value): string
    {
        return serialize($this->mixedValueToScalarConverter->handle($value));
    }
}
