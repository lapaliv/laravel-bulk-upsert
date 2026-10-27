<?php

namespace Tests\Unit\Grammars;

use Illuminate\Database\Query\Grammars\SQLiteGrammar as LaravelSqLiteGrammar;
use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Grammars\SqLiteBulkGrammar;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * A single-branch conditional SET on SQLite is a CASE expression.
 *
 * Regression test. It was compiled into `iif()`, which SQLite has only since 3.32, while
 * Laravel 11 supports SQLite from 3.26: every update with a condition failed there. The test
 * checks the syntax; it does not run on an old SQLite.
 *
 * @internal
 */
final class SqLiteConditionalSetTest extends TestCaseWrapper
{
    /**
     * Run on SQLite only.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDefaultConnection() !== 'sqlite') {
            self::markTestSkipped('The SQLite grammar is checked on the SQLite connection.');
        }
    }

    /**
     * The statement has a CASE, no `iif()`, and the bindings in the order of the placeholders.
     *
     * @return void
     */
    public function testSingleBranchIsCompiledIntoCase(): void
    {
        // arrange
        $builder = (new UpdateBulkBuilder())
            ->setTable(Member::table())
            ->addSet('name', ['id' => 7], 'new')
            ->where('id', '=', 7)
            ->setLimit(1);

        // act
        $grammar = $this->grammar();
        $sql = strtolower($grammar->update($builder));

        // assert
        self::assertStringNotContainsString('iif(', $sql);
        self::assertStringContainsString('case when', $sql);
        self::assertSame(['new'], array_values(array_filter(
            $grammar->getBindings(),
            fn (mixed $binding): bool => $binding === 'new'
        )));
    }

    /**
     * The statement writes the matching row only, NULL included.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testOnlyTheMatchingRowChanges(): void
    {
        // arrange
        DB::table(Member::table())->delete();
        DB::table(Member::table())->insert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'a', 'bio' => 'bio'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'b', 'bio' => 'bio'],
        ]);

        // act
        Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])
            ->update([['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'bio' => null]]);

        // assert
        self::assertSame(
            ['a@x' => null, 'b@x' => 'bio'],
            DB::table(Member::table())->orderBy('email')->pluck('bio', 'email')->all()
        );
    }

    /**
     * Build the bulk grammar of the SQLite connection.
     *
     * @return \Lapaliv\BulkUpsert\Grammars\SqLiteBulkGrammar
     */
    private function grammar(): SqLiteBulkGrammar
    {
        /** @var \Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter $converter */
        $converter = $this->getFromContainer(MixedValueToSqlConverter::class);
        /** @var \Illuminate\Database\Connection $connection */
        $connection = DB::connection();
        /** @var \Illuminate\Database\Query\Grammars\SQLiteGrammar $laravelGrammar */
        $laravelGrammar = clone $connection->getQueryGrammar();

        return new SqLiteBulkGrammar($converter, $laravelGrammar instanceof LaravelSqLiteGrammar ? $laravelGrammar : new LaravelSqLiteGrammar($connection));
    }
}
