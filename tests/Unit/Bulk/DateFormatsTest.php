<?php

namespace Tests\Unit\Bulk;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Enums\Gender;
use Tests\App\Models\User;
use Tests\TestCaseWrapper;

/**
 * A user whose `datetime` cast has a serialization format.
 *
 * @internal
 */
class SerializationFormatUser extends User
{
    /**
     * @var array<string, string>
     */
    protected $casts = [
        'gender' => Gender::class,
        'posts_count' => 'integer',
        'is_admin' => 'boolean',
        'phones' => 'array',
        'last_visited_at' => 'datetime:d/m/Y H:i',
    ];
}

/**
 * A user whose storage format keeps the microseconds.
 *
 * @internal
 */
class MicrosecondsUser extends User
{
    /**
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';
}

/**
 * A user whose date column is cast to an immutable date.
 *
 * @internal
 */
class ImmutableUser extends User
{
    /**
     * @var array<string, string>
     */
    protected $casts = [
        'gender' => Gender::class,
        'posts_count' => 'integer',
        'is_admin' => 'boolean',
        'phones' => 'array',
        'last_visited_at' => 'immutable_datetime',
    ];
}

/**
 * A user whose storage format is a date, while one column still keeps the time.
 *
 * @internal
 */
class DateStorageFormatUser extends User
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $dateFormat = 'Y-m-d';

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'gender' => Gender::class,
        'phones' => 'array',
        'last_visited_at' => 'datetime:Y-m-d H:i',
    ];
}

/**
 * A user whose storage format is a Unix timestamp and which has date-only columns.
 *
 * @internal
 */
class UnixTimestampUser extends User
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    protected $dateFormat = 'U';

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'gender' => Gender::class,
        'phones' => 'array',
        'birthday' => 'date',
    ];
}

/**
 * The same as {@see UnixTimestampUser}, but with an immutable date cast.
 *
 * @internal
 */
class UnixTimestampPlainImmutableUser extends UnixTimestampUser
{
    /**
     * @var array<string, string>
     */
    protected $casts = [
        'gender' => Gender::class,
        'phones' => 'array',
        'birthday' => 'immutable_date',
    ];
}

/**
 * The same as {@see UnixTimestampUser}, but with an immutable date cast with a suffix.
 *
 * @internal
 */
class UnixTimestampImmutableUser extends UnixTimestampUser
{
    /**
     * @var array<string, string>
     */
    protected $casts = [
        'gender' => Gender::class,
        'phones' => 'array',
        'birthday' => 'immutable_date:d.m.Y',
    ];
}

/**
 * The dates must be stored exactly as `Model::save()` stores them.
 *
 * Regression test. The package formatted the dates by the suffix of the `date:...` /
 * `datetime:...` casts, but Laravel uses that suffix for the serialization only: the
 * database gets `Model::fromDateTime()`, which uses `getDateFormat()`. A cast such as
 * `datetime:d/m/Y H:i` therefore stored `25/10/2026 13:45` and lost the seconds.
 *
 * @internal
 */
final class DateFormatsTest extends TestCaseWrapper
{
    /**
     * Freeze the time and clean the users table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-25 13:45:10.123456'));
        // Only the own rows are removed: the other tests leave users with comments behind,
        // and the foreign keys of MySQL and PostgreSQL forbid deleting them.
        DB::table(User::table())->whereIn('email', ['bulk@x.io', 'save@x.io'])->delete();
    }

    /**
     * Unfreeze the time, so the other tests are not affected.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * The serialization format of a cast must not become the storage format.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSerializationFormatIsNotStored(): void
    {
        // act
        $this->assertSameAsSave(SerializationFormatUser::class);

        // assert
        self::assertSame('2026-10-25 13:45:10', $this->raw('bulk@x.io')->last_visited_at);
    }

    /**
     * The storage format of the model must be used for every date column.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testModelDateFormatIsStored(): void
    {
        // act
        $this->assertSameAsSave(MicrosecondsUser::class);

        // assert
        $this->assertStoredDate('2026-10-25 13:45:10.123456', $this->raw('bulk@x.io')->created_at);
    }

    /**
     * An immutable date cast must be stored as a date too.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testImmutableDatetimeIsStored(): void
    {
        // act
        $this->assertSameAsSave(ImmutableUser::class);

        // assert
        self::assertSame('2026-10-25 13:45:10', $this->raw('bulk@x.io')->last_visited_at);
    }

    /**
     * `updated_at` and the updated dates must use the storage format of the model.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdateUsesModelDateFormat(): void
    {
        // arrange
        DB::table(User::table())->insert($this->row('bulk@x.io'));

        // act
        MicrosecondsUser::query()->bulk()->uniqueBy('email')->update([
            ['email' => 'bulk@x.io', 'last_visited_at' => '2026-01-02 03:04:05.000006'],
        ]);

        // assert
        $raw = $this->raw('bulk@x.io');
        $this->assertStoredDate('2026-10-25 13:45:10.123456', $raw->updated_at);
        $this->assertStoredDate('2026-01-02 03:04:05.000006', $raw->last_visited_at);
    }

    /**
     * The dates which already are in the storage format must be kept as they are.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdateOfLoadedModelKeepsStoredDates(): void
    {
        // arrange
        DB::table(User::table())->insert($this->row('bulk@x.io') + ['last_visited_at' => '2026-01-02 03:04:05']);
        /** @var SerializationFormatUser $user */
        $user = SerializationFormatUser::query()->where('email', 'bulk@x.io')->firstOrFail();
        $user->last_visited_at = Carbon::parse('2026-03-04 05:06:07');

        // act
        SerializationFormatUser::query()->bulk()->update([$user]);

        // assert
        self::assertSame('2026-03-04 05:06:07', $this->raw('bulk@x.io')->last_visited_at);
    }

    /**
     * `deleted_at` must use the storage format of the model.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testSoftDeleteUsesModelDateFormat(): void
    {
        // arrange
        DB::table(User::table())->insert($this->row('bulk@x.io'));

        // act
        MicrosecondsUser::query()->bulk()->uniqueBy('email')->delete([['email' => 'bulk@x.io']]);

        // assert
        $this->assertStoredDate('2026-10-25 13:45:10.123456', $this->raw('bulk@x.io')->deleted_at);
    }

    /**
     * A `datetime` column must keep its time when the storage format of the model is a date.
     *
     * Regression test. The package took `'Y-m-d'` in the formats of the date fields for a
     * date-only cast, so a `datetime` column of such a model lost its time.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testDatetimeKeepsTimeWhenModelFormatIsDate(): void
    {
        // arrange
        $attributes = $this->row('save@x.io') + ['last_visited_at' => '2026-10-25 13:45:10'];

        // act
        (new DateStorageFormatUser())->forceFill($attributes)->save();
        DateStorageFormatUser::query()->bulk()->uniqueBy('email')->create(
            [['email' => 'bulk@x.io'] + $attributes]
        );

        // assert
        self::assertSame($this->raw('save@x.io')->last_visited_at, $this->raw('bulk@x.io')->last_visited_at);
        self::assertSame('2026-10-25 13:45:10', $this->raw('bulk@x.io')->last_visited_at);
    }

    /**
     * A date-only column must keep the local day when the storage format is a Unix timestamp.
     *
     * Regression test. `setAttribute()` turns the date into a Unix timestamp, and the package
     * read it back in UTC, so near midnight the stored day moved by one.
     *
     * @param class-string<\Tests\App\Models\User> $class
     * @param string $timezone
     * @param string $value
     * @param string $expected
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    #[DataProvider('unixTimestampDates')]
    public function testDateOnlyKeepsLocalDayOfUnixTimestamp(
        string $class,
        string $timezone,
        string $value,
        string $expected,
    ): void
    {
        // arrange
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set($timezone);

        try {
            // act
            $class::query()->bulk()->uniqueBy('email')->create(
                [['email' => 'bulk@x.io', 'birthday' => $value] + $this->row('bulk@x.io')]
            );

            // assert
            self::assertSame($expected, substr((string)$this->raw('bulk@x.io')->birthday, 0, 10));
        } finally {
            date_default_timezone_set($previousTimezone);
        }
    }

    /**
     * The dates near midnight on both sides of UTC.
     *
     * @return array<string, array{class-string<\Tests\App\Models\User>, string, string, string}>
     */
    public static function unixTimestampDates(): array
    {
        return [
            'date, ahead of UTC' => [UnixTimestampUser::class, 'Pacific/Auckland', '2026-10-25 00:30:00', '2026-10-25'],
            'date, behind UTC' => [UnixTimestampUser::class, 'America/Los_Angeles', '2026-10-25 23:30:00', '2026-10-25'],
            'immutable date' => [UnixTimestampPlainImmutableUser::class, 'Pacific/Auckland', '2026-10-25 00:30:00', '2026-10-25'],
            'immutable date with suffix' => [UnixTimestampImmutableUser::class, 'Pacific/Auckland', '2026-10-25 00:30:00', '2026-10-25'],
        ];
    }

    /**
     * Assert the stored date, with the microseconds where the column keeps them.
     *
     * The fixtures use `dateTime()` without a precision. SQLite keeps the text as it is,
     * while MySQL and PostgreSQL may drop the fraction, so there only the seconds are
     * compared. The fixtures avoid the fractions which would be rounded up.
     *
     * @param string $expected
     * @param string|null $actual
     *
     * @return void
     */
    private function assertStoredDate(string $expected, ?string $actual): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            self::assertSame($expected, $actual);

            return;
        }

        self::assertSame(substr($expected, 0, 19), substr((string)$actual, 0, 19));
    }

    /**
     * Create the same user with `save()` and with the package and compare the stored dates.
     *
     * @param class-string<\Tests\App\Models\User> $class
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    private function assertSameAsSave(string $class): void
    {
        $attributes = [
            'name' => 'name',
            'gender' => 'male',
            'posts_count' => 0,
            'is_admin' => false,
            'last_visited_at' => '2026-10-25 13:45:10',
        ];

        (new $class())->forceFill($attributes + ['email' => 'save@x.io'])->save();
        $class::query()->bulk()->uniqueBy('email')->create([$attributes + ['email' => 'bulk@x.io']]);

        $saved = $this->raw('save@x.io');
        $bulk = $this->raw('bulk@x.io');

        foreach (['last_visited_at', 'created_at', 'updated_at'] as $column) {
            self::assertSame($saved->{$column}, $bulk->{$column}, $column);
        }
    }

    /**
     * Get the raw stored row of the user.
     *
     * @param string $email
     *
     * @return object
     */
    private function raw(string $email): object
    {
        return DB::table(User::table())->where('email', $email)->first()
            ?? self::fail('The user ' . $email . ' is not found.');
    }

    /**
     * Build a raw row of the users table.
     *
     * @param string $email
     *
     * @return array<string, mixed>
     */
    private function row(string $email): array
    {
        return ['email' => $email, 'name' => 'name', 'gender' => 'male', 'posts_count' => 0, 'is_admin' => false];
    }
}
