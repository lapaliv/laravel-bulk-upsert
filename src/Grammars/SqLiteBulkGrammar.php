<?php

namespace Lapaliv\BulkUpsert\Grammars;

use Illuminate\Database\Query\Grammars\SQLiteGrammar as LaravelSqLiteGrammar;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;

/**
 * Compiles the statements for SQLite.
 *
 * @internal
 */
class SqLiteBulkGrammar extends BaseBulkGrammar
{
    /**
     * Create a new grammar instance.
     *
     * @param \Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter $mixedValueToSqlConverter
     * @param \Illuminate\Database\Query\Grammars\SQLiteGrammar $laravelGrammar
     */
    public function __construct(
        MixedValueToSqlConverter $mixedValueToSqlConverter,
        LaravelSqLiteGrammar $laravelGrammar,
    )
    {
        parent::__construct($mixedValueToSqlConverter, $laravelGrammar);
    }

    /**
     * Get the keyword which is placed between `insert` and `into`.
     *
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return string ` or ignore` when the conflicts have to be muted, an empty string otherwise.
     */
    protected function compileInsertIgnoreInfix(InsertBuilder $builder): string
    {
        // `OR IGNORE` also skips NOT NULL and CHECK errors, so it is used only when every
        // conflict may be skipped.
        return $builder->doNothingAtConflict() && !$builder->isIgnoringDuplicatesOnly() ? ' or ignore' : '';
    }

    /**
     * Get the tail of the insert statement.
     *
     * The tail is ` on conflict do nothing` when only a duplicate key may be skipped (the
     * internal insert of an upsert), an empty string otherwise.
     *
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return string
     */
    protected function compileInsertSuffix(InsertBuilder $builder): string
    {
        // `ON CONFLICT DO NOTHING` skips a uniqueness conflict only (SQLite 3.24+).
        return $builder->isIgnoringDuplicatesOnly() ? ' on conflict do nothing' : '';
    }

    /**
     * Compile a CASE with a single WHEN branch.
     *
     * A CASE expression rather than `iif()`: SQLite has `iif()` since 3.32 only, while
     * Laravel 11 supports SQLite from 3.26.
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
