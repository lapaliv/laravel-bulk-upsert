<?php

namespace Lapaliv\BulkUpsert\Drivers\PostgreSql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Grammars\PostgreSqlGrammar;

/**
 * @internal
 */
class PostgreSqlDriverDelete
{
    public function __construct(private MixedValueToSqlConverter $mixedValueToSqlConverter)
    {
        //
    }

    public function handle(ConnectionInterface $connection, DeleteBulkBuilder $builder): int
    {
        $grammar = new PostgreSqlGrammar($this->mixedValueToSqlConverter);

        $result = $connection->delete($grammar->delete($builder), $grammar->getBindings());

        unset($grammar);

        return $result;
    }
}
