<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Drivers\PostgreSql\PostgreSqlInsertResult;
use Lapaliv\BulkUpsert\Grammars\PostgreSqlBulkGrammar;
use LogicException;

/**
 * Runs the bulk queries on a PostgreSQL connection.
 *
 * @internal
 */
readonly class PostgreSqlBulkDriver implements BulkDriver
{
    /**
     * Create a new PostgreSQL driver instance.
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
     * Insert the rows and return the result with the inserted rows.
     *
     * PostgreSQL gives the inserted rows back thanks to the `returning` clause, so the
     * primary key name is not needed here.
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
        $grammar = $this->grammar($connection);

        // PostgreSQL returns the inserted rows right away thanks to `returning`. The statement
        // is an INSERT, so it runs on the writer (`select()` would use the reader), and the
        // connection is marked as modified afterwards, as `insert()` does, so the sticky
        // connections read the new rows from the writer.
        $rows = $connection->select($grammar->insert($builder), $grammar->getBindings(), false);
        $this->laravelConnection($connection)->recordsHaveBeenModified();

        return new PostgreSqlInsertResult($rows);
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
     * @return \Lapaliv\BulkUpsert\Grammars\PostgreSqlBulkGrammar
     */
    private function grammar(ConnectionInterface $connection): PostgreSqlBulkGrammar
    {
        return new PostgreSqlBulkGrammar(
            $this->mixedValueToSqlConverter,
            $this->laravelGrammar($connection)
        );
    }

    /**
     * Narrow the connection to the concrete Laravel connection that {@see PostgresGrammar}
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

    /**
     * Get the query grammar of the connection, configured as the connection uses it.
     *
     * The configured grammar of the connection is cloned rather than a new one created: on
     * Laravel 11 a new grammar does not get the table prefix of the connection (it is set by
     * `setTablePrefix()`), so the statements would go to the table without the prefix.
     *
     * @throws \LogicException When the connection uses a grammar of another kind.
     *
     * @param \Illuminate\Database\ConnectionInterface $connection
     *
     * @return PostgresGrammar
     */
    private function laravelGrammar(ConnectionInterface $connection): PostgresGrammar
    {
        $laravelConnection = $this->laravelConnection($connection);
        $grammar = $laravelConnection->getQueryGrammar();

        if ($grammar instanceof PostgresGrammar) {
            return clone $grammar;
        }

        // A new grammar would lose the configuration of the connection (on Laravel 11, the
        // table prefix), so an unexpected grammar is refused rather than replaced.
        throw new LogicException(sprintf(
            'The connection "%s" uses the query grammar %s, which is not a %s.',
            (string) $laravelConnection->getName(),
            get_debug_type($grammar),
            PostgresGrammar::class,
        ));
    }
}
