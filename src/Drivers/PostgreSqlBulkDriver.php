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

        // PostgreSQL returns the inserted rows right away thanks to `returning`.
        $rows = $connection->select($grammar->insert($builder), $grammar->getBindings());

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
            new PostgresGrammar($this->laravelConnection($connection))
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
}
