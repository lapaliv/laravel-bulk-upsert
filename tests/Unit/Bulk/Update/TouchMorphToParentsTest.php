<?php

namespace Tests\Unit\Bulk\Update;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\App\Models\Model;
use Tests\TestCaseWrapper;

/**
 * A parent of the first class.
 *
 * @internal
 */
class TouchMorphParentA extends Model
{
    /**
     * @var string
     */
    protected $table = 'touch_morph_a';
}

/**
 * A parent of the second class.
 *
 * @internal
 */
class TouchMorphParentB extends Model
{
    /**
     * @var string
     */
    protected $table = 'touch_morph_b';
}

/**
 * A reaction which belongs to a parent of either class and touches it.
 *
 * @internal
 */
class TouchMorphReaction extends Model
{
    /**
     * @var string
     */
    protected $table = 'touch_morph_reactions';

    /**
     * @var string[]
     */
    protected $fillable = ['reactable_type', 'reactable_id', 'body'];

    /**
     * @var string[]
     */
    protected $touches = ['reactable'];

    /**
     * The parent of the reaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }
}

/**
 * The parents of a `morphTo` relation of different classes are touched each in its own table.
 *
 * Regression test. The keys of all the parents were collected together and updated through
 * the model of the first parent: the parent of the other class was not touched, and the row
 * with its key in the table of the first class was touched instead.
 *
 * @internal
 */
final class TouchMorphToParentsTest extends TestCaseWrapper
{
    /**
     * The time of the fixtures.
     *
     * @var string
     */
    private const string BEFORE = '2020-01-01 00:00:00';

    /**
     * Create the tables: A has the rows 1 and 2, B has the row 2.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $schema = DB::connection()->getSchemaBuilder();

        foreach (['touch_morph_reactions', 'touch_morph_a', 'touch_morph_b'] as $table) {
            $schema->dropIfExists($table);
        }

        foreach (['touch_morph_a', 'touch_morph_b'] as $name) {
            $schema->create($name, function (Blueprint $table): void {
                $table->unsignedBigInteger('id')->primary();
                $table->timestamps();
            });
        }

        $schema->create('touch_morph_reactions', function (Blueprint $table): void {
            $table->id();
            $table->string('reactable_type');
            $table->unsignedBigInteger('reactable_id');
            $table->string('body');
            $table->timestamps();
        });

        $times = ['created_at' => self::BEFORE, 'updated_at' => self::BEFORE];
        DB::table('touch_morph_a')->insert([['id' => 1, ...$times], ['id' => 2, ...$times]]);
        DB::table('touch_morph_b')->insert([['id' => 2, ...$times]]);
    }

    /**
     * Drop the tables.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        foreach (['touch_morph_reactions', 'touch_morph_a', 'touch_morph_b'] as $table) {
            $schema->dropIfExists($table);
        }

        parent::tearDown();
    }

    /**
     * A#1 and B#2 are touched, A#2 (the key of B#2 in the table of A) is not.
     *
     * @param bool $bFirst Whether the reaction to B comes first.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('orders')]
    public function testParentsOfDifferentClassesAreTouched(bool $bFirst): void
    {
        // arrange
        $rows = [
            ['reactable_type' => TouchMorphParentA::class, 'reactable_id' => 1, 'body' => 'a'],
            ['reactable_type' => TouchMorphParentB::class, 'reactable_id' => 2, 'body' => 'b'],
        ];
        DB::table('touch_morph_reactions')->insert($bFirst ? array_reverse($rows) : $rows);
        $reactions = TouchMorphReaction::query()->orderBy('id')->get();
        $reactions->each(fn (TouchMorphReaction $reaction) => $reaction->body .= '!');
        Carbon::setTestNow('2026-10-25 13:45:10');

        // act
        TouchMorphReaction::query()->bulk()->update($reactions);

        // assert
        self::assertSame(
            ['A#1' => '2026-10-25 13:45:10', 'A#2' => self::BEFORE, 'B#2' => '2026-10-25 13:45:10'],
            [
                'A#1' => $this->updatedAt('touch_morph_a', 1),
                'A#2' => $this->updatedAt('touch_morph_a', 2),
                'B#2' => $this->updatedAt('touch_morph_b', 2),
            ]
        );
    }

    /**
     * Both orders of the parents.
     *
     * @return array<string, array{bool}>
     */
    public static function orders(): array
    {
        return ['A first' => [false], 'B first' => [true]];
    }

    /**
     * Get the stored update time of a row, cut to the seconds.
     *
     * @param string $table
     * @param int $id
     *
     * @return string
     */
    private function updatedAt(string $table, int $id): string
    {
        return substr((string) DB::table($table)->where('id', $id)->value('updated_at'), 0, 19);
    }
}
