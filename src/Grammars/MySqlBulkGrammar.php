<?php

namespace Lapaliv\BulkUpsert\Grammars;

use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;

/**
 * Compiles the statements for MySQL.
 *
 * @internal
 */
class MySqlBulkGrammar extends BaseBulkGrammar
{
    /**
     * Create a new grammar instance.
     *
     * @param \Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter $mixedValueToSqlConverter
     * @param \Illuminate\Database\Query\Grammars\MySqlGrammar $laravelGrammar
     */
    public function __construct(
        MixedValueToSqlConverter $mixedValueToSqlConverter,
        MySqlGrammar $laravelGrammar,
    )
    {
        parent::__construct($mixedValueToSqlConverter, $laravelGrammar);
    }

    /**
     * Get the keyword which is placed between `insert` and `into`.
     *
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return string ` ignore` when the conflicts have to be muted, an empty string otherwise.
     */
    protected function compileInsertIgnoreInfix(InsertBuilder $builder): string
    {
        return $builder->doNothingAtConflict() ? ' ignore' : '';
    }

    /**
     * Get the tail of the insert statement.
     *
     * MySQL does not need any tail here, so the result is always an empty string.
     *
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return string
     */
    protected function compileInsertSuffix(InsertBuilder $builder): string
    {
        return '';
    }

    /**
     * Compile a CASE with a single WHEN branch into the `if()` function of MySQL.
     *
     * @param string $condition
     * @param string $then
     * @param string $else
     *
     * @return string
     */
    protected function compileConditionalSet(string $condition, string $then, string $else): string
    {
        return sprintf('if(%s, %s, %s)', $condition, $then, $else);
    }

    /**
     * Determine whether the dialect supports LIMIT in update and delete statements.
     *
     * @return bool
     */
    protected function supportsLimit(): bool
    {
        return true;
    }
}
