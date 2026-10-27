<?php

namespace Tests\Unit\Bulk\Update;

use Illuminate\Support\Facades\DB;
use Tests\App\Models\Member;
use Tests\TestCaseWrapper;

/**
 * A member which syncs its original attributes when it is cloned.
 *
 * @internal
 */
class SyncingOnCloneMember extends Member
{
    /**
     * @var string
     */
    protected $table = 'members';

    /**
     * Sync the original attributes of the copy.
     *
     * @return void
     */
    public function __clone()
    {
        $this->syncOriginal();
    }
}

/**
 * A member which cannot be cloned.
 *
 * @internal
 */
class NotCloneableMember extends Member
{
    /**
     * @var string
     */
    protected $table = 'members';

    /**
     * The number of the attempts to clone a member.
     *
     * @var int
     */
    public static int $clones = 0;

    /**
     * Forbid cloning.
     *
     * @return void
     */
    public function __clone()
    {
        self::$clones++;

        throw new \LogicException('The member cannot be cloned.');
    }
}

/**
 * The changes of an updated model must not depend on its `__clone()`.
 *
 * Regression test. The written changes were computed on a clone of the model, so a
 * `__clone()` which synced the original attributes made the written change disappear
 * from `getChanges()`, and a `__clone()` which threw broke the update after the query.
 *
 * @internal
 */
final class ModelCloneTest extends TestCaseWrapper
{
    /**
     * Put one member into the table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table(Member::table())->delete();
        DB::table(Member::table())->insert(
            ['tenant_id' => Member::CURRENT_TENANT, 'email' => 'a@example.com', 'name' => 'old']
        );
        NotCloneableMember::$clones = 0;
    }

    /**
     * A `__clone()` which syncs the original attributes must not hide the written change.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCloneWhichSyncsTheOriginalKeepsTheChanges(): void
    {
        // arrange
        /** @var SyncingOnCloneMember $member */
        $member = SyncingOnCloneMember::query()->firstOrFail();
        $member->name = 'new';

        // act
        SyncingOnCloneMember::query()->bulk()->update([$member]);

        // assert
        self::assertSame('new', DB::table(Member::table())->value('name'));
        self::assertSame('new', $member->getChanges()['name'] ?? null);
        self::assertFalse($member->isDirty('name'));
    }

    /**
     * A model which cannot be cloned must be updated and keep the changes.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testModelWhichCannotBeClonedIsUpdated(): void
    {
        // arrange
        /** @var NotCloneableMember $member */
        $member = NotCloneableMember::query()->firstOrFail();
        $member->name = 'new';

        // act
        NotCloneableMember::query()->bulk()->update([$member]);

        // assert
        self::assertSame(0, NotCloneableMember::$clones);
        self::assertSame('new', DB::table(Member::table())->value('name'));
        self::assertSame('new', $member->getChanges()['name'] ?? null);
    }

    /**
     * A cast object held by the caller must stay bound over two updates.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testHeldCastObjectStaysBoundOverTwoUpdates(): void
    {
        // arrange
        DB::table(Member::table())->update(['settings' => '{}']);
        /** @var Member $member */
        $member = Member::query()->firstOrFail();
        $settings = $member->settings;
        $sut = Member::query()->bulk();

        // act
        $settings['first'] = 1;
        $sut->update([$member]);
        $settings['second'] = 2;
        $sut->update([$member]);
        $settings['third'] = 3;

        // assert
        self::assertTrue($member->isDirty('settings'));
        self::assertSame(
            ['first' => 1, 'second' => 2],
            json_decode((string) DB::table(Member::table())->value('settings'), true)
        );
    }
}
