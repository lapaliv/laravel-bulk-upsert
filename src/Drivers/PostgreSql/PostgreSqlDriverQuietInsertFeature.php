<?php

namespace Lapaliv\BulkUpsert\Drivers\PostgreSql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Grammars\PostgreSqlGrammar;

/**
 * @internal
 */
class PostgreSqlDriverQuietInsertFeature
{
    public function __construct(
        private MixedValueToSqlConverter $mixedValueToSqlConverter,
    ) {
        //
    }

    public function handle(ConnectionInterface $connection, InsertBuilder $builder): void
    {
        $grammar = new PostgreSqlGrammar($this->mixedValueToSqlConverter);

        $connection->insert($grammar->insert($builder), $grammar->getBindings());

        unset($grammar);
    }
}
