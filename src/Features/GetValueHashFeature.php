<?php

namespace Lapaliv\BulkUpsert\Features;

use Lapaliv\BulkUpsert\Converters\MixedValueToScalarConverter;

/**
 * @internal
 */
class GetValueHashFeature
{
    public function __construct(private MixedValueToScalarConverter $mixedValueToScalarConverter)
    {
        //
    }

    public function handle(mixed $value): string
    {
        return hash('crc32c', $this->mixedValueToScalarConverter->handle($value) . ':' . gettype($value));
    }
}
