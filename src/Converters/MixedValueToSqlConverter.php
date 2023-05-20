<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

namespace Lapaliv\BulkUpsert\Converters;

use Illuminate\Database\Query\Expression;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression;

/**
 * @internal
 */
class MixedValueToSqlConverter
{
    /**
     * @param mixed $value
     * @param mixed[] $bindings
     *
     * @return string
     */
    public function handle(mixed $value, array &$bindings): string
    {
        if ($value instanceof Expression) {
            $value = $value->getValue();

            if (is_bool($value) || is_int($value) || $value === null) {
                return $this->handle($value, $bindings);
            }

            return $value;
        }

        if ($value instanceof BuilderRawExpression) {
            return $value->get();
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        $bindings[] = $value;

        return '?';
    }
}
