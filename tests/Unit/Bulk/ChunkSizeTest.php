<?php

namespace Tests\Unit\Bulk;

use Tests\App\Models\User;
use Tests\TestCaseWrapper;
use Tests\Unit\UserTestTrait;

/**
 * `chunk()` must bound the size of the statements the package emits.
 *
 * Regression test. {@see \Lapaliv\BulkUpsert\Bulk::getReadyChunks()} used to split the
 * accumulation in its non-forced branch only:
 *
 *     if ($force) {
 *         $accumulation->setUpdateOnly($this->updateOnly);
 *         $accumulation->setUpdateExcept($this->updateExcept);
 *         yield $accumulation;            // the whole accumulation, unsplit
 *         unset($this->storage[$storageKey][$key]);
 *     } elseif (count($accumulation->getRows()) >= $this->chunkSize) {
 *         ...array_chunk(...)             // the only place chunkSize is read
 *     }
 *
 * `create()`, `update()`, `upsert()`, `delete()`, `forceDelete()`, every `*AndReturn()` and every
 * `*Accumulated()` method call `getReadyChunks(..., force: true)`, so `$this->chunkSize` is never
 * consulted on any of those paths. Only the `*OrAccumulate()` family is chunked, and even there
 * chunking is a side effect of the flush threshold rather than of the write itself.
 *
 * Consequence: `->chunk(100)->create($hundredThousandRows)` built a single INSERT with 100 000
 * value tuples — past `max_allowed_packet` on MySQL and past the 65535-parameter limit on
 * PostgreSQL — while holding every model and every binding in memory at once. The forced
 * branch now runs the rows through `array_chunk()` as well, so flushing bounds the statement
 * size instead of bypassing the limit.
 *
 * @internal
 */
final class ChunkSizeTest extends TestCaseWrapper
{
    use UserTestTrait;

    /**
     * The chunk size which every test in this class puts on the builder.
     *
     * @var int
     */
    private const int CHUNK_SIZE = 2;

    /**
     * The number of rows which every test in this class writes.
     *
     * @var int
     */
    private const int ROWS = 6;

    /**
     * `create()` must emit one INSERT per chunk.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCreateSplitsRowsIntoChunks(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(self::ROWS);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk(self::CHUNK_SIZE);

        // act
        $queries = $this->recordQueries(
            static fn () => $sut->create($users)
        );

        // assert
        self::assertCount(
            (int) ceil(self::ROWS / self::CHUNK_SIZE),
            $this->filterByStatement($queries, 'insert'),
            sprintf(
                'Expected %d rows at chunk size %d to be inserted in %d statements.',
                self::ROWS,
                self::CHUNK_SIZE,
                (int) ceil(self::ROWS / self::CHUNK_SIZE)
            )
        );

        $users->each(
            fn (User $user) => $this->userWasCreated($user)
        );
    }

    /**
     * `update()` must emit one UPDATE per chunk.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testUpdateSplitsRowsIntoChunks(): void
    {
        // arrange
        $users = $this->userGenerator->createCollectionAndDirty(self::ROWS);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['id'])
            ->chunk(self::CHUNK_SIZE);

        // act
        $queries = $this->recordQueries(
            static fn () => $sut->update($users)
        );

        // assert
        self::assertCount(
            (int) ceil(self::ROWS / self::CHUNK_SIZE),
            $this->filterByStatement($queries, 'update'),
            sprintf(
                'Expected %d rows at chunk size %d to be updated in %d statements.',
                self::ROWS,
                self::CHUNK_SIZE,
                (int) ceil(self::ROWS / self::CHUNK_SIZE)
            )
        );

        $users->each(
            fn (User $user) => $this->userWasUpdated($user)
        );
    }

    /**
     * Chunking must not disturb what `createAndReturn()` hands back.
     *
     * The `*AndReturn()` methods collect their result from a `savedMany` listener registered
     * inside the `getReadyChunks()` loop. Once that loop yields more than one chunk the listener
     * is registered more than once, so this pins that every model still comes back exactly once.
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function testCreateAndReturnKeepsOneModelPerRowAcrossChunks(): void
    {
        // arrange
        $users = $this->userGenerator->makeCollection(self::ROWS + 1);
        $sut = User::query()
            ->bulk()
            ->uniqueBy(['email'])
            ->chunk(self::CHUNK_SIZE);

        // act
        $result = $sut->createAndReturn($users);

        // assert
        self::assertCount($users->count(), $result);
        self::assertCount(
            $users->count(),
            array_unique($result->pluck('id')->all()),
            'Every row must be returned exactly once.'
        );
    }

    /**
     * Run the callback with the query log enabled and return what was logged.
     *
     * @param callable $callback
     *
     * @return array<int, array{query: string}>
     */
    private function recordQueries(callable $callback): array
    {
        /** @var \Illuminate\Database\Connection $connection */
        $connection = User::query()->getConnection();

        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $callback();
        } finally {
            $connection->disableQueryLog();
        }

        return $connection->getQueryLog();
    }

    /**
     * Keep only the logged queries whose SQL starts with the given statement.
     *
     * @param array<int, array{query: string}> $queries
     * @param string $statement
     *
     * @return array<int, array{query: string}>
     */
    private function filterByStatement(array $queries, string $statement): array
    {
        return array_values(
            array_filter(
                $queries,
                static fn (array $query): bool => str_starts_with(
                    ltrim(strtolower($query['query'])),
                    $statement
                )
            )
        );
    }
}
