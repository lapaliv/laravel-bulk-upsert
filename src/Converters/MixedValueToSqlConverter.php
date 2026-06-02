<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

namespace Lapaliv\BulkUpsert\Converters;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression;

/**
 * @internal
 */
class MixedValueToSqlConverter
{
    public function __construct(
        private MixedValueToScalarConverter $mixedValueToScalarConverter
    )
    {
        //
    }

    /**
     * @param Grammar $grammar
     * @param mixed $value
     * @param mixed[] $bindings
     *
     * @return string
     */
    public function handle(Grammar $grammar, mixed $value, array &$bindings): string
    {
        if ($value instanceof Expression) {
            $value = $value->getValue($grammar);
        }

        if ($value instanceof BuilderRawExpression) {
            return $value->get();
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        $bindings[] = $this->mixedValueToScalarConverter->handle($value);

        return '?';
    }
}
