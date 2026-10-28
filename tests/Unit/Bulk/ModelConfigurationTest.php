<?php

namespace Tests\Unit\Bulk;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * The rows given as arrays must keep the configuration of the model given to the bulk.
 *
 * Regression test. The arrays were turned into models through the container by the
 * class name, so a table set with `setTable()` and the casts added with `mergeCasts()`
 * were lost: the rows were written into the default table, while the existing rows were
 * looked up in the configured one.
 *
 * @internal
 */
final class ModelConfigurationTest extends TestCaseWrapper
{
    /**
     * The table which the model is switched to.
     *
     * @var string
     */
    private const string ARCHIVE = 'members_archive';

    /**
     * The name of the other connection.
     *
     * @var string
     */
    private const string CONNECTION = 'bulk_other_connection';

    /**
     * Create the archive table and clean both tables.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $schema = DB::getSchemaBuilder();
        $schema->dropIfExists(self::ARCHIVE);
        $schema->create(self::ARCHIVE, function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('tenant_id');
            $table->string('email');
            $table->string('name');
            $table->text('bio')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
        });
        DB::table(Member::table())->delete();
    }

    /**
     * Drop the archive table.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        DB::getSchemaBuilder()->dropIfExists(self::ARCHIVE);

        parent::tearDown();
    }

    /**
     * `create()` must write into the table of the given model.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCreateUsesTheTableOfTheModel(): void
    {
        // act
        $this->archive()->newQuery()->bulk()->uniqueBy('email')->create([$this->row('a@example.com', 'a')]);

        // assert
        self::assertSame(['a'], DB::table(self::ARCHIVE)->pluck('name')->all());
        self::assertSame(0, DB::table(Member::table())->count());
    }

    /**
     * `upsert()` must find and write the rows in the table of the given model.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpsertUsesTheTableOfTheModel(): void
    {
        // arrange
        DB::table(self::ARCHIVE)->insert(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@example.com', 'name' => 'old']);

        // act
        $this->archive()->newQuery()->bulk()->uniqueBy('email')->upsert([
            $this->row('a@example.com', 'updated'),
            $this->row('b@example.com', 'created'),
        ]);

        // assert
        self::assertSame(['updated', 'created'], DB::table(self::ARCHIVE)->orderBy('email')->pluck('name')->all());
        self::assertSame(0, DB::table(Member::table())->count());
    }

    /**
     * `createAndReturn()` must give the models back with the configuration of the given model.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testReturnedModelsKeepTheTableOfTheModel(): void
    {
        // act
        $result = $this->archive()->newQuery()->bulk()->uniqueBy('email')->createAndReturn([
            $this->row('a@example.com', 'a'),
        ]);

        // assert
        self::assertCount(1, $result);
        self::assertSame(self::ARCHIVE, $result->first()?->getTable());
    }

    /**
     * The casts added to the given model must be used for the rows given as arrays.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testMergedCastsAreUsed(): void
    {
        // arrange
        $model = $this->archive();
        $model->mergeCasts(['bio' => 'array']);

        // act
        $model->newQuery()->bulk()->uniqueBy('email')->create([
            $this->row('a@example.com', 'a') + ['bio' => ['key' => 'value']],
        ]);

        // assert
        self::assertSame(['key' => 'value'], json_decode((string) DB::table(self::ARCHIVE)->value('bio'), true));
    }

    /**
     * `upsert()` must find and write the rows through the connection of the given model.
     *
     * The other connection is a separate SQLite database in memory; the default one keeps
     * a control row which must stay untouched.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpsertUsesTheConnectionOfTheModel(): void
    {
        // arrange
        DB::connectUsing(self::CONNECTION, ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], true);

        try {
            DB::connection(self::CONNECTION)->getSchemaBuilder()->create(Member::table(), function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->string('email');
                $table->string('name');
                $table->text('bio')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'email']);
            });
            DB::connection(self::CONNECTION)->table(Member::table())->insert(
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@example.com', 'name' => 'old']
            );
            DB::table(Member::table())->insert(
                ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@example.com', 'name' => 'control']
            );
            $model = (new Member())->setConnection(self::CONNECTION);

            // act
            $model->newQuery()->bulk()->uniqueBy('email')->upsert([
                $this->row('a@example.com', 'updated'),
                $this->row('b@example.com', 'created'),
            ]);

            // assert
            self::assertSame(
                ['updated', 'created'],
                DB::connection(self::CONNECTION)->table(Member::table())->orderBy('email')->pluck('name')->all()
            );
            self::assertSame(['control'], DB::table(Member::table())->pluck('name')->all());
        } finally {
            DB::purge(self::CONNECTION);
        }
    }

    /**
     * Get a member model switched to the archive table.
     *
     * @return \Tests\App\Models\Member
     */
    private function archive(): Member
    {
        return (new Member())->setTable(self::ARCHIVE);
    }

    /**
     * Build an input row of the current tenant.
     *
     * @param string $email
     * @param string $name
     *
     * @return array<string, mixed>
     */
    private function row(string $email, string $name): array
    {
        return ['tenant_id' => Member::CURRENT_TENANT, 'email' => $email, 'name' => $name];
    }
}
