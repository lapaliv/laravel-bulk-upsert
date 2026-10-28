<?php

namespace Lapaliv\BulkUpsert\Grammars;

use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;

/**
 * Compiles the statements for PostgreSQL.
 *
 * @internal
 */
class PostgreSqlBulkGrammar extends BaseBulkGrammar
{
    /**
     * Create a new grammar instance.
     *
     * @param \Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter $mixedValueToSqlConverter
     * @param \Illuminate\Database\Query\Grammars\PostgresGrammar $laravelGrammar
     */
    public function __construct(
        MixedValueToSqlConverter $mixedValueToSqlConverter,
        PostgresGrammar $laravelGrammar,
    )
    {
        parent::__construct($mixedValueToSqlConverter, $laravelGrammar);
    }

    /**
     * Get the keyword which is placed between `insert` and `into`.
     *
     * PostgreSQL mutes the conflicts in the tail of the statement, so the result
     * is always an empty string.
     *
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return string
     */
    protected function compileInsertIgnoreInfix(InsertBuilder $builder): string
    {
        return '';
    }

    /**
     * Get the tail of the insert statement.
     *
     * The tail keeps ` on conflict do nothing` when the conflicts have to be muted
     * and the ` returning ...` part when the builder asks for some columns back.
     *
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return string
     */
    protected function compileInsertSuffix(InsertBuilder $builder): string
    {
        $result = $builder->doNothingAtConflict() ? ' on conflict do nothing' : '';

        if (!empty($builder->getSelect())) {
            $result .= sprintf(
                ' returning %s',
                implode(
                    ',',
                    array_map(
                        fn(string $column) => $this->wrap($column),
                        $builder->getSelect()
                    )
                )
            );
        }

        return $result;
    }

    /**
     * Compile a CASE with a single WHEN branch into a plain CASE expression.
     *
     * @param string $condition
     * @param string $then
     * @param string $else
     *
     * @return string
     */
    protected function compileConditionalSet(string $condition, string $then, string $else): string
    {
        return sprintf('case when %s then %s else %s end', $condition, $then, $else);
    }

    /**
     * Determine whether the dialect supports LIMIT in update and delete statements.
     *
     * @return bool
     */
    protected function supportsLimit(): bool
    {
        return false;
    }
}
