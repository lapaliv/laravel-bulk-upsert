<?php

namespace Tests\Unit\Bulk;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\App\Models\Model;
use Tests\TestCaseWrapper;

/**
 * An item stored through a connection with a table prefix.
 *
 * @internal
 */
class PrefixedItem extends Model
{
    use SoftDeletes;

    /**
     * @var string
     */
    protected $connection = 'bulk_prefixed';

    /**
     * @var string
     */
    protected $table = 'prefix_items';

    /**
     * @var string[]
     */
    protected $fillable = ['code', 'name'];
}

/**
 * Every statement of the library goes to the prefixed table of the connection.
 *
 * A table without the prefix and with the same structure and keys is a decoy: a statement
 * which loses the prefix writes there.
 *
 * @internal
 */
final class TablePrefixTest extends TestCaseWrapper
{
    /**
     * The name of the connection with the prefix.
     *
     * @var string
     */
    private const string CONNECTION = 'bulk_prefixed';

    /**
     * Register the prefixed connection and create the prefixed table and the decoy.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $config = DB::connection()->getConfig();
        $config['prefix'] = 'bulk_prefix_';
        self::addTestConnection($config, self::CONNECTION);

        // SQLite keeps the index names in one namespace, so the decoy names its index itself.
        foreach ([[DB::connection(self::CONNECTION), null], [DB::connection(), 'decoy_prefix_items_code']] as [$connection, $index]) {
            $schema = $connection->getSchemaBuilder();
            $schema->dropIfExists('prefix_items');
            $schema->create('prefix_items', function (Blueprint $table) use ($index): void {
                $table->id();
                $table->string('code');
                // `->unique(null)` on a column would set the attribute to null and make no index.
                $table->unique('code', $index);
                $table->string('name');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        DB::table('prefix_items')->insert(['id' => 1, 'code' => 'a', 'name' => 'decoy']);
    }

    /**
     * Drop the tables and the connection.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        DB::connection(self::CONNECTION)->getSchemaBuilder()->dropIfExists('prefix_items');
        DB::connection()->getSchemaBuilder()->dropIfExists('prefix_items');
        self::removeTestConnection(self::CONNECTION);

        parent::tearDown();
    }

    /**
     * Create, update, upsert and delete through the prefixed connection.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testEveryStatementUsesThePrefix(): void
    {
        // act
        $created = PrefixedItem::query()->bulk()->uniqueBy('code')->createAndReturn([
            ['code' => 'a', 'name' => 'created'],
            ['code' => 'b', 'name' => 'created'],
        ]);
        PrefixedItem::query()->bulk()->uniqueBy('code')->update([['code' => 'a', 'name' => 'updated']]);
        PrefixedItem::query()->bulk()->uniqueBy('code')->upsert([
            ['code' => 'b', 'name' => 'upserted'],
            ['code' => 'c', 'name' => 'inserted'],
        ]);
        PrefixedItem::query()->bulk()->uniqueBy('code')->delete([['code' => 'c']]);
        PrefixedItem::query()->bulk()->uniqueBy('code')->forceDelete([['code' => 'b']]);

        // assert
        self::assertSame(['a', 'b'], $created->pluck('code')->sort()->values()->all());
        self::assertSame(
            ['a' => 'updated', 'c' => 'inserted'],
            DB::connection(self::CONNECTION)->table('prefix_items')->orderBy('code')->pluck('name', 'code')->all()
        );
        self::assertNotNull(DB::connection(self::CONNECTION)->table('prefix_items')->where('code', 'c')->value('deleted_at'));
        self::assertSame(
            [['code' => 'a', 'name' => 'decoy', 'deleted_at' => null]],
            DB::table('prefix_items')->get(['code', 'name', 'deleted_at'])->map(fn ($row) => (array) $row)->all()
        );
    }

    /**
     * The one-by-one insert after a duplicate key uses the prefix as well.
     *
     * Another connection inserts `d` right before the first INSERT of the upsert, so the
     * statement fails with a duplicate key and the rows go one by one.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testOneByOneInsertUsesThePrefix(): void
    {
        // arrange
        $connection = DB::connection(self::CONNECTION);
        $armed = true;
        $connection->beforeExecuting(function (string $query) use (&$armed, $connection): void {
            if (!$armed || !str_starts_with(strtolower(ltrim($query)), 'insert into')) {
                return;
            }

            $armed = false;
            $other = (new \Illuminate\Database\Connectors\ConnectionFactory(new \Illuminate\Container\Container()))
                ->make($connection->getConfig(), 'prefix_race');
            $other->table('prefix_items')->insert(['code' => 'd', 'name' => 'theirs']);
            $other->disconnect();
        });

        // act
        try {
            PrefixedItem::query()->bulk()->uniqueBy('code')->upsert([
                ['code' => 'd', 'name' => 'mine'],
                ['code' => 'e', 'name' => 'mine'],
            ]);
        } finally {
            $armed = false;
        }

        // assert
        self::assertSame(
            ['d' => 'mine', 'e' => 'mine'],
            $connection->table('prefix_items')->orderBy('code')->pluck('name', 'code')->all()
        );
        self::assertSame(['a'], DB::table('prefix_items')->pluck('code')->all());
    }
}
