<?php

namespace Tests\Unit\Grammars;

use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Grammars\MySqlBulkGrammar;
use Lapaliv\BulkUpsert\Grammars\PostgreSqlBulkGrammar;
use Lapaliv\BulkUpsert\Grammars\SqLiteBulkGrammar;
use RuntimeException;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * An IN list of the package grammar must match exactly its values.
 *
 * Regression test. An empty list was rendered as `column is null`, so it matched the rows
 * with NULL instead of nothing.
 *
 * @internal
 */
final class WhereInValuesTest extends TestCaseWrapper
{
    /**
     * Put members with a NULL bio, a bio `x` and a bio `y` into the table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
        DB::table(Member::table())->insert([
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'old', 'bio' => null],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'b@x', 'name' => 'old', 'bio' => 'x'],
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'c@x', 'name' => 'old', 'bio' => 'y'],
        ]);
    }

    /**
     * The statement updates exactly the rows of the list.
     *
     * @param array<int, string|null> $values
     * @param string[] $expected The names ordered by the email.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('lists')]
    public function testListMatchesExactlyItsValues(array $values, array $expected): void
    {
        // arrange
        $builder = (new UpdateBulkBuilder())
            ->setTable(Member::table())
            ->addSimpleSet('name', 'new')
            ->whereIn('bio', $values);
        $grammar = $this->grammar();

        // act
        DB::update($grammar->update($builder), $grammar->getBindings());

        // assert
        self::assertSame($expected, DB::table(Member::table())->orderBy('email')->pluck('name')->all());
    }

    /**
     * The lists and the rows they match.
     *
     * @return array<string, array{array<int, string|null>, string[]}>
     */
    public static function lists(): array
    {
        return [
            'empty list' => [[], ['old', 'old', 'old']],
            'only null' => [[null], ['new', 'old', 'old']],
            'null and a value' => [[null, 'x'], ['new', 'new', 'old']],
            'values' => [['x', 'y'], ['old', 'new', 'new']],
        ];
    }

    /**
     * A list with NULL stays grouped inside the surrounding conditions.
     *
     * @return void
     */
    public function testListWithNullStaysGrouped(): void
    {
        // arrange
        $builder = (new UpdateBulkBuilder())
            ->setTable(Member::table())
            ->addSimpleSet('name', 'new')
            ->whereIn('bio', [null, 'x'])
            ->where('email', '=', 'b@x');
        $grammar = $this->grammar();

        // act
        DB::update($grammar->update($builder), $grammar->getBindings());

        // assert
        self::assertSame(['old', 'new', 'old'], DB::table(Member::table())->orderBy('email')->pluck('name')->all());
    }

    /**
     * Get the bulk grammar of the connection the suite runs against.
     *
     * @return \Lapaliv\BulkUpsert\Grammars\MySqlBulkGrammar|\Lapaliv\BulkUpsert\Grammars\PostgreSqlBulkGrammar|\Lapaliv\BulkUpsert\Grammars\SqLiteBulkGrammar
     */
    private function grammar(): MySqlBulkGrammar|PostgreSqlBulkGrammar|SqLiteBulkGrammar
    {
        /** @var \Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter $converter */
        $converter = $this->app->make(MixedValueToSqlConverter::class);
        /** @var \Illuminate\Database\Connection $connection */
        $connection = DB::connection();

        return match ($driver = $connection->getDriverName()) {
            'mysql' => new MySqlBulkGrammar($converter, new \Illuminate\Database\Query\Grammars\MySqlGrammar($connection)),
            'pgsql' => new PostgreSqlBulkGrammar($converter, new \Illuminate\Database\Query\Grammars\PostgresGrammar($connection)),
            'sqlite' => new SqLiteBulkGrammar($converter, new \Illuminate\Database\Query\Grammars\SQLiteGrammar($connection)),
            default => throw new RuntimeException('Unsupported driver ' . $driver),
        };
    }
}
