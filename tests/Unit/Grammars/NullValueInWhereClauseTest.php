<?php

namespace Tests\Unit\Grammars;

use Illuminate\Database\Query\Grammars\MySqlGrammar as LaravelMySqlGrammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar as LaravelPostgresGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar as LaravelSqLiteGrammar;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkGrammar;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Grammars\MySqlBulkGrammar;
use Lapaliv\BulkUpsert\Grammars\PostgreSqlBulkGrammar;
use Lapaliv\BulkUpsert\Grammars\SqLiteBulkGrammar;
use RuntimeException;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * A `null` in a WHERE condition must be rendered as `is null`, not as `= null`.
 *
 * Regression test. {@see \Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter::handle()} renders `null` as the SQL
 * literal `null`:
 *
 *     if ($value === null) {
 *         return 'null';
 *     }
 *
 * That is correct inside an INSERT value list, but the same converter is used for WHERE
 * conditions and for the `when` filters of the generated `case` expressions (see
 * `BaseBulkGrammar::getSqlWhereClause()`, shared by the three dialects). `col = null` is `unknown` in
 * three-valued logic and therefore never matches a row.
 *
 * The path is reached from {@see \Lapaliv\BulkUpsert\Builders\WhereClauseApplier::addCondition()},
 * which routes nulls there explicitly:
 *
 *     if (is_scalar($value) || $value === null) {
 *         $builder->where($column, '=', $value);
 *     }
 *
 * Laravel's own query builder rewrites `where($column, '=', null)` into `whereNull()`. This one
 * did not — which is why the existence SELECT (built on the Eloquent builder) found the row while
 * the bulk UPDATE that followed silently missed it.
 *
 * The fix lives in the three grammars rather than in the converter, so that `null` keeps its
 * literal form where it belongs (an INSERT value list) and only comparisons are rewritten:
 * `= null` becomes `is null`, `!=` and `<>` become `is not null`, and a `null` inside a `whereIn`
 * gets its own `or ... is null` branch instead of being swallowed by `in (...)`.
 *
 * @internal
 */
final class NullValueInWhereClauseTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * The generated UPDATE must not contain a `= null` comparison.
     *
     * The statement is built by hand with a null and a non-null condition, and the rendered SQL
     * has to hold `is null` instead of `= null`.
     *
     * @return void
     */
    public function testUpdateRendersNullAsIsNull(): void
    {
        // arrange
        $builder = (new UpdateBulkBuilder())
            ->setTable('users')
            ->addSet('name', ['email' => 'john@example.com', 'avatar' => null], 'John')
            ->where('avatar', '=', null)
            ->where('email', '=', 'john@example.com')
            ->setLimit(1);

        // act
        $sql = $this->grammar()->update($builder);

        // assert
        self::assertStringNotContainsStringIgnoringCase(
            '= null',
            $this->normalize($sql),
            'A null comparison must be rendered as `is null`; `= null` never matches a row. '
            . 'Generated SQL: ' . $sql
        );
        self::assertStringContainsStringIgnoringCase('is null', $this->normalize($sql));
    }

    /**
     * End-to-end: a row whose unique key contains a `null` must still be updated.
     *
     * `avatar` is nullable, so `uniqueBy(['email', 'avatar'])` produces a WHERE clause with a
     * null on one side. The row exists — `MarkNonexistentRowsAsSkippedStep` finds it through the
     * Eloquent builder and does not skip it — yet the UPDATE affects zero rows.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdateMatchesARowWhoseUniqueKeyContainsNull(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(
            2,
            ['avatar' => null],
            ['avatar' => null],
        );

        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email', 'avatar']);

        // act
        $sut->update($users);

        // assert
        $users->each(
            fn (User $user) => $this->userWasUpdated($user)
        );
    }

    /**
     * Normalise whitespace so `=  null` and `=\nnull` are caught too.
     *
     * @param string $sql
     *
     * @return string
     */
    private function normalize(string $sql): string
    {
        return preg_replace('/\s+/', ' ', $sql);
    }

    /**
     * The bulk grammar matching the connection the suite currently runs against.
     *
     * @return \Lapaliv\BulkUpsert\Contracts\BulkGrammar
     *
     * @throws \RuntimeException when the driver of the connection is not supported
     */
    private function grammar(): BulkGrammar
    {
        /** @var \Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter $converter */
        $converter = $this->getFromContainer(MixedValueToSqlConverter::class);

        /** @var \Illuminate\Database\Connection $connection */
        $connection = User::query()->getConnection();

        return match ($driver = $connection->getDriverName()) {
            'mysql', 'mariadb' => new MySqlBulkGrammar(
                $converter,
                new LaravelMySqlGrammar($connection)
            ),
            'pgsql' => new PostgreSqlBulkGrammar(
                $converter,
                new LaravelPostgresGrammar($connection)
            ),
            'sqlite' => new SqLiteBulkGrammar(
                $converter,
                new LaravelSqLiteGrammar($connection)
            ),
            default => throw new RuntimeException("Unsupported driver [{$driver}]"),
        };
    }
}
