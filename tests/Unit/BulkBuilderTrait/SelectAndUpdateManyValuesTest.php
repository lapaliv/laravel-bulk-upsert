<?php

namespace Tests\Unit\BulkBuilderTrait;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\App\Models\Member;
use Tests\App\Models\Story;
use Tests\TestCaseWrapper;

/**
 * A member whose bio is hashed.
 *
 * @internal
 */
class HashedBioMember extends Member
{
    /**
     * @var string
     */
    protected $table = 'members';

    /**
     * @var array<string, string>
     */
    protected $casts = ['bio' => 'hashed'];
}

/**
 * A member whose bio mutator reads another attribute of the row.
 *
 * @internal
 */
class RowMutatorMember extends Member
{
    /**
     * @var string
     */
    protected $table = 'members';

    /**
     * Sign the bio with the email of the row.
     *
     * @param string $value
     *
     * @return void
     */
    public function setBioAttribute(string $value): void
    {
        $this->attributes['bio'] = $value . ' for ' . ($this->attributes['email'] ?? '?');
    }
}

/**
 * A member which has a listener, so the rows are selected and updated one chunk at a time.
 *
 * @internal
 */
class ListenedMember extends Member
{
    /**
     * @var string
     */
    protected $table = 'members';

    /**
     * Register a listener.
     *
     * @return void
     */
    protected static function booted(): void
    {
        parent::booted();

        static::updating(fn () => true);
    }
}

/**
 * A member which records the order in which its rows are updated.
 *
 * @internal
 */
class OrderRecordingMember extends Member
{
    /**
     * @var string
     */
    protected $table = 'members';

    /**
     * The emails in the order of the updating events.
     *
     * @var string[]
     */
    public static array $updated = [];

    /**
     * Record every updated row.
     *
     * @return void
     */
    protected static function booted(): void
    {
        parent::booted();

        static::updating(function (self $member): void {
            self::$updated[] = $member->email;
        });
    }
}

/**
 * A member whose global scope orders the rows by the email descending.
 *
 * @internal
 */
class OrderedByEmailDescMember extends Member
{
    /**
     * @var string
     */
    protected $table = 'members';

    /**
     * The emails in the order of the updating events.
     *
     * @var string[]
     */
    public static array $updated = [];

    /**
     * Order the rows and record every updated row.
     *
     * @return void
     */
    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('ordered', fn (\Illuminate\Database\Eloquent\Builder $builder) => $builder->orderByDesc('email'));
        static::updating(function (self $member): void {
            self::$updated[] = $member->email;
        });
    }
}

/**
 * A member whose global scope limits the rows.
 *
 * @internal
 */
class LimitedByScopeMember extends Member
{
    /**
     * @var string
     */
    protected $table = 'members';

    /**
     * Take three rows after the first one by the email.
     *
     * @return void
     */
    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope(
            'limited',
            fn (\Illuminate\Database\Eloquent\Builder $builder) => $builder->orderBy('email')->offset(1)->limit(3)
        );
    }
}

/**
 * A member whose global scope adds a top-level `orWhere()`.
 *
 * @internal
 */
class OrWhereScopeMember extends Member
{
    /**
     * @var string
     */
    protected $table = 'members';

    /**
     * The number of the updating events; the test stops a loop with it.
     *
     * @var int
     */
    public static int $updates = 0;

    /**
     * Add the scope and count the updated rows.
     *
     * @return void
     */
    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope(
            'or_where',
            fn (\Illuminate\Database\Eloquent\Builder $builder) => $builder->orWhere('name', 'pending')
        );
        static::updating(function (): void {
            self::$updates++;

            if (self::$updates > 50) {
                throw new \LogicException('The rows are selected again and again.');
            }
        });
    }
}

/**
 * `selectAndUpdateMany()` must write the values exactly as the model would, and every row.
 *
 * Regression test. Without listeners the values were sent to a plain UPDATE as they were,
 * so the `hashed` cast and the mutators did not run (a password was stored as plain
 * text). With listeners the rows were paginated with OFFSET, so when the update changed
 * a column of the WHERE clause, the next pages skipped the rows which had moved up.
 *
 * @internal
 */
final class SelectAndUpdateManyValuesTest extends TestCaseWrapper
{
    /**
     * Clean the members table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
    }

    /**
     * The `hashed` cast must run without listeners.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testHashedCastRunsWithoutListeners(): void
    {
        // arrange
        $this->insert(['a@example.com']);

        // act
        HashedBioMember::query()->where('email', 'a@example.com')->selectAndUpdateMany(['bio' => 'secret']);

        // assert
        $stored = (string) DB::table(Member::table())->value('bio');
        self::assertNotSame('secret', $stored);
        self::assertTrue(Hash::check('secret', $stored));
    }

    /**
     * A mutator which reads the row must run for every row.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowMutatorRunsForEveryRow(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com']);

        // act
        RowMutatorMember::query()->selectAndUpdateMany(['bio' => 'hello']);

        // assert
        self::assertSame(
            ['hello for a@example.com', 'hello for b@example.com'],
            DB::table(Member::table())->orderBy('email')->pluck('bio')->all()
        );
    }

    /**
     * Every row must be updated when the update moves the rows out of the WHERE clause.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowsLeavingTheWhereClauseAreNotSkipped(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com', 'c@example.com', 'd@example.com', 'e@example.com']);

        // act
        $count = ListenedMember::query()
            ->where('name', 'pending')
            ->selectAndUpdateMany(['name' => 'done'], 'email', 2);

        // assert
        self::assertSame(5, $count);
        self::assertSame(0, DB::table(Member::table())->where('name', 'pending')->count());
    }

    /**
     * The given builder must not be changed by the method.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testGivenBuilderIsNotChanged(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com']);
        $builder = ListenedMember::query()->where('name', 'pending');
        $sql = $builder->toSql();

        // act
        $builder->selectAndUpdateMany(['bio' => 'x'], 'email', 1);

        // assert
        self::assertSame($sql, $builder->toSql());
    }

    /**
     * The order, the limit and the offset of the builder must select the rows to update.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testOrderLimitAndOffsetSelectTheRows(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com', 'c@example.com', 'd@example.com', 'e@example.com']);

        // act
        $count = ListenedMember::query()
            ->where('name', 'pending')
            ->orderByDesc('email')
            ->offset(1)
            ->limit(3)
            ->selectAndUpdateMany(['name' => 'done'], 'email', 2);

        // assert
        self::assertSame(3, $count);
        self::assertSame(
            ['b@example.com', 'c@example.com', 'd@example.com'],
            DB::table(Member::table())->where('name', 'done')->orderBy('email')->pluck('email')->all()
        );
    }

    /**
     * A chunk size below one must be rejected before any query.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testChunkBelowOneIsRejected(): void
    {
        // assert
        $this->expectException(\InvalidArgumentException::class);

        // act
        Member::query()->selectAndUpdateMany(['name' => 'x'], null, 0);
    }

    /**
     * A builder which selects some columns only must still update every row.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testPartialSelectUpdatesEveryRow(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com', 'c@example.com']);
        $builder = Member::query()->select(['email']);
        $sql = $builder->toSql();

        // act
        $count = $builder->selectAndUpdateMany(['name' => 'done'], null, 2);

        // assert
        self::assertSame(3, $count);
        self::assertSame(0, DB::table(Member::table())->where('name', 'pending')->count());
        self::assertSame($sql, $builder->toSql());
    }

    /**
     * A builder with some columns, an order, a limit and an offset must update its rows.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testPartialSelectWithOrderLimitAndOffset(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com', 'c@example.com', 'd@example.com']);

        // act
        $count = Member::query()
            ->select(['email'])
            ->orderBy('email')
            ->offset(1)
            ->limit(2)
            ->selectAndUpdateMany(['name' => 'done'], null, 1);

        // assert
        self::assertSame(2, $count);
        self::assertSame(
            ['b@example.com', 'c@example.com'],
            DB::table(Member::table())->where('name', 'done')->orderBy('email')->pluck('email')->all()
        );
    }

    /**
     * A mutator which reads the row must get the whole row with a partial select.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowMutatorGetsTheWholeRowWithPartialSelect(): void
    {
        // arrange
        $this->insert(['a@example.com']);

        // act
        RowMutatorMember::query()->select(['name'])->selectAndUpdateMany(['bio' => 'hello']);

        // assert
        self::assertSame('hello for a@example.com', DB::table(Member::table())->value('bio'));
    }

    /**
     * A string primary key must be paginated over several chunks.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testStringPrimaryKeyIsPaginated(): void
    {
        // arrange
        DB::table(Story::table())->delete();
        DB::table(Story::table())->insert(array_map(
            fn (int $i) => ['uuid' => (string) Str::uuid(), 'title' => 'pending', 'content' => 'c' . $i],
            range(1, 5)
        ));

        // act
        $count = Story::query()->where('title', 'pending')->selectAndUpdateMany(['title' => 'done'], null, 2);

        // assert
        self::assertSame(5, $count);
        self::assertSame(0, DB::table(Story::table())->where('title', 'pending')->count());
    }

    /**
     * A top-level `orWhere()` must not escape the pagination over several chunks.
     *
     * The update does not change the WHERE columns, so a cursor which applied to one branch
     * only would select the first rows again and again.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testOrWhereStaysInsideThePagination(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com', 'c@example.com', 'd@example.com', 'e@example.com']);

        // act
        $count = ListenedMember::query()
            ->where('name', 'pending')
            ->orWhere('email', 'missing@example.com')
            ->selectAndUpdateMany(['bio' => 'seen'], null, 2);

        // assert
        self::assertSame(5, $count);
        self::assertSame(5, DB::table(Member::table())->where('bio', 'seen')->count());
    }

    /**
     * The rows must be updated in the order of the builder.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testRowsAreUpdatedInTheOrderOfTheBuilder(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com', 'c@example.com', 'd@example.com']);
        OrderRecordingMember::$updated = [];

        // act
        OrderRecordingMember::query()
            ->orderByDesc('email')
            ->selectAndUpdateMany(['bio' => 'x'], null, 3);

        // assert
        self::assertSame(['d@example.com', 'c@example.com', 'b@example.com', 'a@example.com'], OrderRecordingMember::$updated);
    }

    /**
     * A grouped query must be rejected before any write.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testGroupedQueryIsRejectedBeforeAnyWrite(): void
    {
        // arrange
        $this->insert(['a@example.com']);

        // act
        try {
            Member::query()->select(['name'])->groupBy('name')->selectAndUpdateMany(['name' => 'done']);
            self::fail('A grouped query has to be rejected.');
        } catch (\LogicException) {
            // expected
        }

        // assert
        self::assertSame('pending', DB::table(Member::table())->value('name'));
    }

    /**
     * An order added by a global scope must keep the rows selected by the builder.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testOrderOfGlobalScopeIsHonoured(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com', 'c@example.com']);
        OrderedByEmailDescMember::$updated = [];

        // act
        OrderedByEmailDescMember::query()->selectAndUpdateMany(['bio' => 'x'], null, 2);

        // assert
        self::assertSame(['c@example.com', 'b@example.com', 'a@example.com'], OrderedByEmailDescMember::$updated);
    }

    /**
     * A limit and an offset added by a global scope must select the rows only once.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testLimitAndOffsetOfGlobalScopeAreHonoured(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com', 'c@example.com', 'd@example.com', 'e@example.com']);

        // act
        $count = LimitedByScopeMember::query()->selectAndUpdateMany(['name' => 'done'], null, 1);

        // assert
        self::assertSame(3, $count);
        self::assertSame(
            ['b@example.com', 'c@example.com', 'd@example.com'],
            DB::table(Member::table())->where('name', 'done')->orderBy('email')->pluck('email')->all()
        );
    }

    /**
     * The bindings of a raw select must not leak into the queries of the method.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testBindingsOfRawSelectDoNotLeak(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com']);

        // act
        $count = Member::query()
            ->selectRaw('? as marker', ['x'])
            ->where('name', 'pending')
            ->orderBy('email')
            ->selectAndUpdateMany(['name' => 'done'], null, 1);

        // assert
        self::assertSame(2, $count);
        self::assertSame(0, DB::table(Member::table())->where('name', 'pending')->count());
    }

    /**
     * A top-level `orWhere()` of a global scope must stay inside the pagination.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testOrWhereOfGlobalScopeStaysInsideThePagination(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com', 'c@example.com', 'd@example.com', 'e@example.com']);
        OrWhereScopeMember::$updates = 0;

        // act
        $count = OrWhereScopeMember::query()->selectAndUpdateMany(['bio' => 'seen'], null, 2);

        // assert
        self::assertSame(5, $count);
        self::assertSame(5, OrWhereScopeMember::$updates);
    }

    /**
     * A distinct query ordered by another column must update its rows.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDistinctOrderedQueryIsUpdated(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com', 'c@example.com']);

        // act
        // A real DISTINCT query selects comparable columns: PostgreSQL has no equality for the
        // `json` column, so `distinct members.*` would not run even without the package.
        $builder = Member::query()->select(['members.id', 'members.email'])->distinct()->orderBy('email');
        $builder->clone()->get();

        $count = $builder->selectAndUpdateMany(['name' => 'done'], null, 2);

        // assert
        self::assertSame(3, $count);
        self::assertSame(0, DB::table(Member::table())->where('name', 'pending')->count());
    }

    /**
     * A DISTINCT query without the primary key must be rejected before any write.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDistinctQueryWithoutKeyIsRejected(): void
    {
        // arrange
        $this->insert(['a@example.com']);

        // act
        try {
            Member::query()->select(['email'])->distinct()->orderBy('email')->selectAndUpdateMany(['name' => 'done']);
            self::fail('A DISTINCT query without the primary key has to be rejected.');
        } catch (\LogicException) {
            // expected
        }

        try {
            // Without an own order the keyset branch runs, which has to reject it as well.
            Member::query()->select(['email'])->distinct()->selectAndUpdateMany(['name' => 'done']);
            self::fail('A DISTINCT query without the primary key has to be rejected.');
        } catch (\LogicException) {
            // expected
        }

        // assert
        self::assertSame('pending', DB::table(Member::table())->value('name'));
    }

    /**
     * A join must not change which rows are updated.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testJoinUpdatesTheRowsOfTheModel(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com']);
        $aliasTable = Member::table();

        // act
        $count = Member::query()
            ->join($aliasTable . ' as other', 'other.id', '=', $aliasTable . '.id')
            ->where('other.email', 'a@example.com')
            ->selectAndUpdateMany(['name' => 'done']);

        // assert
        self::assertSame(1, $count);
        self::assertSame(['done', 'pending'], DB::table(Member::table())->orderBy('email')->pluck('name')->all());
    }

    /**
     * A table alias of the builder must be honoured in both branches.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testTableAliasIsHonoured(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com', 'c@example.com']);

        // act
        $keyset = Member::query()->withoutGlobalScopes()->from(Member::table() . ' as m')
            ->where('m.email', 'a@example.com')
            ->selectAndUpdateMany(['name' => 'keyset']);
        $snapshot = Member::query()->withoutGlobalScopes()->from(Member::table() . ' as m')
            ->where('m.email', '!=', 'a@example.com')
            ->orderBy('m.email')
            ->selectAndUpdateMany(['name' => 'snapshot'], null, 1);

        // assert
        self::assertSame([1, 2], [$keyset, $snapshot]);
        self::assertSame(
            ['keyset', 'snapshot', 'snapshot'],
            DB::table(Member::table())->orderBy('email')->pluck('name')->all()
        );
    }

    /**
     * An order by a computed column of the select must keep working.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testOrderByComputedColumnIsHonoured(): void
    {
        // arrange
        $this->insert(['ccc@example.com', 'a@example.com', 'bb@example.com']);
        OrderRecordingMember::$updated = [];

        // act
        $count = OrderRecordingMember::query()
            ->select(['*'])
            ->selectRaw('length(email) + ? as email_rank', [1])
            ->orderBy('email_rank')
            ->selectAndUpdateMany(['bio' => 'x'], null, 2);

        // assert
        self::assertSame(3, $count);
        self::assertSame(['a@example.com', 'bb@example.com', 'ccc@example.com'], OrderRecordingMember::$updated);
    }

    /**
     * A distinct query without an own order must not be widened to every column.
     *
     * PostgreSQL has no equality for the `json` column, so `distinct members.*` fails there.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDistinctQueryWithoutOrderIsUpdated(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com', 'c@example.com']);

        // act
        $count = Member::query()
            ->select(['members.id', 'members.email'])
            ->distinct()
            ->selectAndUpdateMany(['name' => 'done'], null, 2);

        // assert
        self::assertSame(3, $count);
        self::assertSame(0, DB::table(Member::table())->where('name', 'pending')->count());
    }

    /**
     * A join with another `id` must not confuse the keys of an ordered query.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testJoinWithAnotherIdInOrderedQuery(): void
    {
        // arrange
        $this->insert(['a@example.com', 'b@example.com']);
        $table = Member::table();

        // act
        $count = Member::query()
            ->select([$table . '.*', 'other.id as id'])
            ->join($table . ' as other', 'other.email', '!=', $table . '.email')
            ->where('other.email', 'b@example.com')
            ->orderBy($table . '.email')
            ->selectAndUpdateMany(['name' => 'done'], null, 1);

        // assert
        self::assertSame(1, $count);
        self::assertSame(['done', 'pending'], DB::table($table)->orderBy('email')->pluck('name')->all());
    }

    /**
     * Put pending members with the given emails into the table.
     *
     * @param string[] $emails
     *
     * @return void
     */
    private function insert(array $emails): void
    {
        DB::table(Member::table())->insert(array_map(
            fn (string $email) => ['tenant_id' => Member::CURRENT_TENANT, 'email' => $email, 'name' => 'pending'],
            $emails
        ));
    }
}
