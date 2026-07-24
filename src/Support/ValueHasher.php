<?php

namespace Lapaliv\BulkUpsert\Support;

use Lapaliv\BulkUpsert\Converters\MixedValueToScalarConverter;

/**
 * Makes a short hash from a value of any type.
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
     * Hash the given value with the `crc32c` algorithm.
     *
     * The type of the value is a part of the hash, so values of different types never
     * give the same hash.
     *
     * @param mixed $value
     *
     * @return string
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported
     */
    public function handle(mixed $value): string
    {
        return hash('crc32c', (string)$this->mixedValueToScalarConverter->handle($value) . ':' . gettype($value));
    }
}
