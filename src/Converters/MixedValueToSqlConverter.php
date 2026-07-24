<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

namespace Lapaliv\BulkUpsert\Converters;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar;
use Lapaliv\BulkUpsert\Builders\Clauses\BuilderRawExpression;

/**
 * Converts a value into a piece of SQL, collecting the bindings on the way.
 *
 * @internal
 */
readonly class MixedValueToSqlConverter
{
    /**
     * Create a new converter instance.
     *
     * @param \Lapaliv\BulkUpsert\Converters\MixedValueToScalarConverter $mixedValueToScalarConverter
     *
     * @return void
     */
    public function __construct(
        private MixedValueToScalarConverter $mixedValueToScalarConverter
    )
    {
        //
    }

    /**
     * Convert the given value into its SQL representation.
     *
     * Expressions and raw expressions give their own SQL. Numbers, booleans and `null`
     * are inlined. Any other value is added to `$bindings` and replaced by a `?`
     * placeholder.
     *
     * @param \Illuminate\Database\Query\Grammars\Grammar $grammar
     * @param mixed $value
     * @param mixed[] $bindings
     *
     * @return string
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported
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
