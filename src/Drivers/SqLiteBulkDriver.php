<?php

namespace Lapaliv\BulkUpsert\Drivers;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Grammars\SQLiteGrammar as LaravelSqLiteGrammar;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriver;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Drivers\SqLite\SqLiteInsertResult;
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
                )
            );

            $lastPrimaryBeforeInserting = $lastRow->id ?? 0;
        }

        $grammar = new SqLiteBulkGrammar($this->mixedValueToSqlConverter, $laravelGrammar);
        $connection->insert($grammar->insert($builder), $grammar->getBindings());

        return new SqLiteInsertResult(
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
