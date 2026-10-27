<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Grammars\SQLiteGrammar as LaravelSqLiteGrammar;
use Illuminate\Database\QueryException;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Drivers\SqLite\SqLiteInsertResult;
use Lapaliv\BulkUpsert\Drivers\SqLite\SqLitePartialInsert;
use Lapaliv\BulkUpsert\Grammars\SqLiteBulkGrammar;
use LogicException;

/**
 * Runs the bulk queries on a SQLite connection.
 *
 * @internal
 */
readonly class SqLiteBulkDriver implements BulkDriver
{
    /**
     * Create a new SQLite driver instance.
     *
     * @param \Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter $mixedValueToSqlConverter
     *
     * @return void
     */
    public function __construct(
        private MixedValueToSqlConverter $mixedValueToSqlConverter,
    )
    {
        //
    }

    /**
     * Insert the rows and return the result which helps to find them again.
     *
     * SQLite cannot return the inserted rows, so the driver reads the biggest value of
     * the primary key before inserting and puts it into the result.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     * @param string|null $primaryKeyName
     *
     * @return \Lapaliv\BulkUpsert\Contracts\BulkInsertResult
     */
    public function insertWithResult(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
    ): BulkInsertResult
    {
        $laravelGrammar = new LaravelSqLiteGrammar($this->laravelConnection($connection));
        $lastPrimaryBeforeInserting = null;

        // SQLite cannot return the inserted rows, so the driver remembers the last
        // primary key value before inserting and selects everything above it later.
        if ($primaryKeyName !== null) {
            /** @var object{id?: int|string|null}|null $lastRow */
            $lastRow = $connection->selectOne(
                sprintf(
                    'select max(%s) as id from %s',
                    $laravelGrammar->wrap($primaryKeyName),
                    $laravelGrammar->wrapTable(
                        $builder->getTable() ?? throw new LogicException('The insert builder must have a target table before running the query.')
                    )
                ),
                [],
                // The rows inserted after this maximum are read back, so it has to come from
                // the writer: a lagging reader would give a stale maximum.
                false,
            );

            $lastPrimaryBeforeInserting = $lastRow->id ?? 0;
        }

        $insertedIndexes = $this->runInsert($connection, $builder);

        return new SqLiteInsertResult(
            is_numeric($lastPrimaryBeforeInserting)
                ? (int)$lastPrimaryBeforeInserting
                : null,
            $insertedIndexes,
        );
    }

    /**
     * Run the insert and tell which rows it has created.
     *
     * `ON CONFLICT DO NOTHING` does not say which rows it has skipped, so when the duplicates
     * have to be skipped, the rows are inserted by a plain statement first: it is atomic and
     * creates all of them. Only when it fails with a duplicate key, they are inserted one by
     * one, skipping the duplicates, and each one tells by its count whether it was created.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return list<int>|null The positions of the created rows, or null when it is unknown.
     *
     * @throws \Throwable
     */
    private function runInsert(ConnectionInterface $connection, InsertBuilder $builder): ?array
    {
        $all = array_keys(array_values($builder->getValues()));

        if (!$builder->isIgnoringDuplicatesOnly()) {
            $grammar = $this->grammar($connection);
            $count = $connection->affectingStatement($grammar->insert($builder), $grammar->getBindings());

            // `INSERT OR IGNORE` or a trigger with `RAISE(IGNORE)` may skip rows without
            // telling which ones: only a full count proves that every row was created.
            return !$builder->doNothingAtConflict() && $count === count($all) ? $all : null;
        }

        $plain = (clone $builder)->onConflictDoNothing(false)->ignoreDuplicatesOnly(false);

        try {
            // The attempt runs in its own transaction (a savepoint inside an outer one), so a
            // failure leaves none of its rows, whatever conflict resolution a trigger uses.
            // A trigger with `RAISE(IGNORE)` skips a row without an error: then the count is
            // short, the attempt is undone and the rows go one by one, so each one tells.
            /** @var list<int> */
            return $connection->transaction(function () use ($connection, $plain, $all): array {
                $grammar = $this->grammar($connection);
                $count = $connection->affectingStatement($grammar->insert($plain), $grammar->getBindings());

                if ($count !== count($all)) {
                    throw new SqLitePartialInsert();
                }

                return $all;
            });
        } catch (SqLitePartialInsert) {
            // Undone by the transaction; the rows are inserted one by one below.
        } catch (QueryException $exception) {
            if (!$this->isDuplicateKey($exception)) {
                throw $exception;
            }
        }

        /** @var list<int> */
        return $connection->transaction(function () use ($connection, $builder): array {
            $inserted = [];

            foreach (array_values($builder->getValues()) as $index => $row) {
                $grammar = $this->grammar($connection);
                $count = $connection->affectingStatement(
                    $grammar->insert($builder->withValues([$row])),
                    $grammar->getBindings()
                );

                if ($count > 0) {
                    $inserted[] = $index;
                }
            }

            return $inserted;
        });
    }

    /**
     * Determine whether the statement has failed because of a duplicate key.
     *
     * SQLite gives the same code (19, SQLITE_CONSTRAINT) to every constraint and the extended
     * code is not exposed by PDO, so the message tells a unique or primary key apart.
     *
     * @param \Illuminate\Database\QueryException $exception
     *
     * @return bool
     */
    private function isDuplicateKey(QueryException $exception): bool
    {
        /** @var array<int, mixed>|null $errorInfo */
        $errorInfo = $exception->errorInfo;

        return (int)($errorInfo[1] ?? 0) === 19
            && str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }

    /**
     * Insert the rows without collecting any result.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return void
     */
    public function quietInsert(ConnectionInterface $connection, InsertBuilder $builder): void
    {
        $grammar = $this->grammar($connection);

        $connection->insert($grammar->insert($builder), $grammar->getBindings());
    }

    /**
     * Run the update query and return the number of the affected rows.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param \Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder $builder
     *
     * @return int
     */
    public function update(ConnectionInterface $connection, UpdateBulkBuilder $builder): int
    {
        $grammar = $this->grammar($connection);

        return $connection->update($grammar->update($builder), $grammar->getBindings());
    }

    /**
     * Run the delete query and return the number of the deleted rows.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param \Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder $builder
     *
     * @return int
     */
    public function forceDelete(ConnectionInterface $connection, DeleteBulkBuilder $builder): int
    {
        $grammar = $this->grammar($connection);

        return $connection->delete($grammar->delete($builder), $grammar->getBindings());
    }

    /**
     * Create the package grammar for the given connection.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     *
     * @return \Lapaliv\BulkUpsert\Grammars\SqLiteBulkGrammar
     */
    private function grammar(ConnectionInterface $connection): SqLiteBulkGrammar
    {
        return new SqLiteBulkGrammar(
            $this->mixedValueToSqlConverter,
            new LaravelSqLiteGrammar($this->laravelConnection($connection))
        );
    }

    /**
     * Narrow the connection to the concrete Laravel connection that {@see LaravelSqLiteGrammar}
     * requires.
     *
     * Every connection the package receives here is created by Laravel's own database
     * manager, which always returns a {@see Connection} instance, even though the driver
     * contract is typed against the wider {@see ConnectionInterface}.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     *
     * @return \Illuminate\Database\Connection
     */
    private function laravelConnection(ConnectionInterface $connection): Connection
    {
        if ($connection instanceof Connection) {
            return $connection;
        }

        throw new LogicException(
            sprintf('Expected an instance of %s, got %s.', Connection::class, $connection::class)
        );
    }
}
