<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Grammars\MySqlGrammar as LaravelMySqlGrammar;
use Illuminate\Database\QueryException;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Drivers\MySql\MySqlInsertResult;
use Lapaliv\BulkUpsert\Grammars\MySqlBulkGrammar;
use LogicException;

/**
 * Runs the bulk queries on a MySQL connection.
 *
 * @internal
 */
readonly class MySqlBulkDriver implements BulkDriver
{
    /**
     * Create a new MySQL driver instance.
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
     * MySQL cannot return the inserted rows, so the driver reads the biggest value of
     * the primary key before inserting and puts it into the result.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     * @param string|null $primaryKeyName
     *
     * @return \Lapaliv\BulkUpsert\Contracts\BulkInsertResult
     *
     * @throws \Throwable
     */
    public function insertWithResult(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
    ): BulkInsertResult
    {
        $laravelGrammar = new LaravelMySqlGrammar($this->getLaravelConnection($connection));
        $lastPrimaryBeforeInserting = null;

        // MySQL cannot return the inserted rows, so the driver remembers the last
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

        $this->runInsert($connection, $builder);

        return new MySqlInsertResult(
            is_numeric($lastPrimaryBeforeInserting)
                ? (int)$lastPrimaryBeforeInserting
                : null
        );
    }

    /**
     * Insert the rows without collecting any result.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function quietInsert(ConnectionInterface $connection, InsertBuilder $builder): void
    {
        $this->runInsert($connection, $builder);
    }

    /**
     * Run the insert, skipping only the duplicate keys when the builder asks for that.
     *
     * MySQL has no `ON CONFLICT DO NOTHING`: `INSERT IGNORE` also turns NOT NULL, CHECK and
     * foreign key errors into warnings, and `ON DUPLICATE KEY UPDATE` fires the UPDATE
     * triggers of the conflicting rows. So the rows are inserted by a plain statement; only
     * when it fails with a duplicate key (1062), they are inserted one by one inside a
     * transaction, skipping the duplicates. Any other error rolls the rows of the statement
     * back and reaches the caller.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param \Lapaliv\BulkUpsert\Builders\InsertBuilder $builder
     *
     * @return void
     *
     * @throws \Throwable
     */
    private function runInsert(ConnectionInterface $connection, InsertBuilder $builder): void
    {
        $run = function (InsertBuilder $statement) use ($connection): void {
            $grammar = $this->grammar($connection);
            $connection->insert($grammar->insert($statement), $grammar->getBindings());
        };

        if (!$builder->isIgnoringDuplicatesOnly()) {
            $run($builder);

            return;
        }

        try {
            // A single statement is atomic: when it fails, none of its rows is stored.
            $run($builder);

            return;
        } catch (QueryException $exception) {
            if (!$this->isDuplicateKey($exception)) {
                throw $exception;
            }
        }

        $connection->transaction(function () use ($builder, $run): void {
            foreach ($builder->getValues() as $row) {
                try {
                    $run($builder->withValues([$row]));
                } catch (QueryException $exception) {
                    if (!$this->isDuplicateKey($exception)) {
                        throw $exception;
                    }
                }
            }
        });
    }

    /**
     * Determine whether the statement has failed because of a duplicate key.
     *
     * The native code is checked: SQLSTATE 23000 covers other integrity errors as well.
     *
     * @param \Illuminate\Database\QueryException $exception
     *
     * @return bool
     */
    private function isDuplicateKey(QueryException $exception): bool
    {
        /** @var array<int, mixed>|null $errorInfo */
        $errorInfo = $exception->errorInfo;

        return (int)($errorInfo[1] ?? 0) === 1062;
    }

    /**
     * Run the update query and return the number of the affected rows.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     * @param \Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder $builder
     *
     * @return int
     *
     * @throws \Throwable
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
     * @return \Lapaliv\BulkUpsert\Grammars\MySqlBulkGrammar
     */
    private function grammar(ConnectionInterface $connection): MySqlBulkGrammar
    {
        return new MySqlBulkGrammar(
            $this->mixedValueToSqlConverter,
            new LaravelMySqlGrammar($this->getLaravelConnection($connection))
        );
    }

    /**
     * Narrow the connection to the concrete Laravel connection that {@see LaravelMySqlGrammar}
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
    private function getLaravelConnection(ConnectionInterface $connection): Connection
    {
        if ($connection instanceof Connection) {
            return $connection;
        }

        throw new LogicException(
            sprintf('Expected an instance of %s, got %s.', Connection::class, $connection::class)
        );
    }
}
