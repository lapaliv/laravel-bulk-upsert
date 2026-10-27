<?php

namespace Tests\Unit\Bulk;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Lapaliv\BulkUpsert\Exceptions\BulkModelDoesNotMatch;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * A model bound to another table or connection than the bulk is rejected before any query.
 *
 * Regression test. A model of the same class bound to another table was written through the
 * table of the bulk: the row with the same primary key in the bulk's table was changed.
 *
 * @internal
 */
final class ForeignModelTest extends TestCaseWrapper
{
    /**
     * The id of the row in both tables.
     *
     * @var int
     */
    private int $id;

    /**
     * Create the second table with the same structure and one row in each table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $schema = DB::connection()->getSchemaBuilder();
        $schema->dropIfExists('members_b');
        $schema->create('members_b', function (Blueprint $table): void {
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
        // The id comes from the sequence of `members` (an explicit id would leave the PostgreSQL
        // sequence behind); the row of `members_b` gets the same id on purpose.
        $this->id = (int) DB::table(Member::table())->insertGetId(
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'A']
        );
        DB::table('members_b')->insert(['id' => $this->id, 'tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@x', 'name' => 'B']);
    }

    /**
     * Drop the second table.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        DB::connection()->getSchemaBuilder()->dropIfExists('members_b');

        parent::tearDown();
    }

    /**
     * Every writing method rejects a model of another table and writes nothing.
     *
     * @param string $method
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('methods')]
    public function testModelOfAnotherTableIsRejected(string $method): void
    {
        // arrange
        if (str_starts_with($method, 'create')) {
            // New rows: the old code wrote the foreign one into the bulk's table.
            $own = new Member(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'new@x', 'name' => 'own new']);
            $foreign = (new Member())->setTable('members_b')
                ->fill(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'other@x', 'name' => 'foreign new']);
        } else {
            $own = Member::query()->findOrFail($this->id);
            $own->name = 'own changed';
            $foreign = $this->foreign();
            $foreign->name = 'foreign changed';
        }

        // act
        try {
            Member::query()->bulk()->uniqueBy(['tenant_id', 'email'])->{$method}([$own, $foreign]);
            $exception = null;
        } catch (BulkModelDoesNotMatch $caught) {
            $exception = $caught;
        }

        // assert
        self::assertNotNull($exception);
        self::assertSame($foreign, $exception->getModel());
        self::assertSame(['A'], DB::table(Member::table())->pluck('name')->all());
        self::assertSame(['B'], DB::table('members_b')->pluck('name')->all());
    }

    /**
     * The writing methods.
     *
     * @return array<string, array{string}>
     */
    public static function methods(): array
    {
        return [
            'create' => ['create'],
            'update' => ['update'],
            'upsert' => ['upsert'],
            'delete' => ['delete'],
            'forceDelete' => ['forceDelete'],
            'updateOrAccumulate' => ['updateOrAccumulate'],
            'createAndReturn' => ['createAndReturn'],
            'updateAndReturn' => ['updateAndReturn'],
            'upsertAndReturn' => ['upsertAndReturn'],
            'upsertOrAccumulate' => ['upsertOrAccumulate'],
            'deleteOrAccumulate' => ['deleteOrAccumulate'],
        ];
    }

    /**
     * A rejected model does not leave the models given with it in the accumulated storage.
     *
     * @return void
     */
    public function testRejectedCallAccumulatesNothing(): void
    {
        // arrange
        $own = Member::query()->findOrFail($this->id);
        $own->name = 'own changed';
        $sut = Member::query()->bulk();

        // act
        try {
            $sut->updateOrAccumulate([$own, $this->foreign()]);
        } catch (BulkModelDoesNotMatch) {
            //
        }
        $sut->updateAccumulated();

        // assert
        self::assertSame(['A'], DB::table(Member::table())->pluck('name')->all());
    }

    /**
     * The rows accumulated by an earlier call stay after a later call is rejected.
     *
     * @return void
     */
    public function testEarlierAccumulatedRowsSurviveTheRejection(): void
    {
        // arrange
        $own = Member::query()->findOrFail($this->id);
        $own->name = 'own changed';
        $sut = Member::query()->bulk()->chunk(100);
        $sut->updateOrAccumulate([$own]);

        // act
        try {
            $sut->updateOrAccumulate([$this->foreign()]);
        } catch (BulkModelDoesNotMatch) {
            //
        }
        $sut->updateAccumulated();

        // assert
        self::assertSame(['own changed'], DB::table(Member::table())->pluck('name')->all());
        self::assertSame(['B'], DB::table('members_b')->pluck('name')->all());
    }

    /**
     * A model rebound to another table after it was accumulated is rejected at the flush.
     *
     * @return void
     */
    public function testModelReboundAfterAccumulationIsRejectedAtTheFlush(): void
    {
        // arrange
        $model = Member::query()->findOrFail($this->id);
        $model->name = 'changed';
        $sut = Member::query()->bulk()->chunk(100);
        $sut->updateOrAccumulate([$model]);
        $model->setTable('members_b');

        // act
        try {
            $sut->updateAccumulated();
            $exception = null;
        } catch (BulkModelDoesNotMatch $caught) {
            $exception = $caught;
        }

        // assert
        self::assertNotNull($exception);
        self::assertSame(['A'], DB::table(Member::table())->pluck('name')->all());
        self::assertSame(['B'], DB::table('members_b')->pluck('name')->all());

        // The row stays accumulated: once the model is bound back, the next flush writes it.
        $model->setTable(Member::table());
        $sut->updateAccumulated();
        self::assertSame(['changed'], DB::table(Member::table())->pluck('name')->all());
    }

    /**
     * A model of another connection is rejected even if the table is the same.
     *
     * @return void
     */
    public function testModelOfAnotherConnectionIsRejected(): void
    {
        // arrange
        $other = array_values(array_diff(['sqlite', 'mysql', 'pgsql'], [DB::getDefaultConnection()]))[0];
        $model = new Member(['tenant_id' => Member::CURRENT_TENANT, 'email' => 'c@x', 'name' => 'C']);
        $model->setConnection($other);

        // act
        $this->expectException(BulkModelDoesNotMatch::class);

        Member::query()->bulk()->create([$model]);
    }

    /**
     * Naming the default connection explicitly is the same connection.
     *
     * @return void
     */
    public function testDefaultConnectionNamedExplicitlyIsAccepted(): void
    {
        // arrange
        $model = Member::query()->findOrFail($this->id);
        $model->setConnection(DB::getDefaultConnection());
        $model->name = 'changed';

        // act
        Member::query()->bulk()->update([$model]);

        // assert
        self::assertSame(['changed'], DB::table(Member::table())->pluck('name')->all());
    }

    /**
     * A bulk built on a model of the other table writes into that table.
     *
     * @return void
     */
    public function testBulkOfTheOtherTableAcceptsItsModels(): void
    {
        // arrange
        $foreign = $this->foreign();
        $foreign->name = 'changed';

        // act
        (new Member())->setTable('members_b')->newQuery()->bulk()->update([$foreign]);

        // assert
        self::assertSame(['A'], DB::table(Member::table())->pluck('name')->all());
        self::assertSame(['changed'], DB::table('members_b')->pluck('name')->all());
    }

    /**
     * Load the member of the second table.
     *
     * @return \Tests\App\Models\Member
     */
    private function foreign(): Member
    {
        /** @var \Tests\App\Models\Member */
        return (new Member())->setTable('members_b')->newQuery()->findOrFail($this->id);
    }
}
