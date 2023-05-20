<?php

namespace Lapaliv\BulkUpsert\Drivers\PostgreSql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Entities\BulkPostgreSqlInsertResult;
use Lapaliv\BulkUpsert\Grammars\PostgreSqlGrammar;

class PostgreSqlDriverInsertWithResult
{
    public function __construct(
        private MixedValueToSqlConverter $mixedValueToSqlConverter,
    ) {
        //
    }

    public function handle(ConnectionInterface $connection, InsertBuilder $builder): BulkInsertResult
    {
        $grammar = new PostgreSqlGrammar($this->mixedValueToSqlConverter);

        $rows = $connection->select($grammar->insert($builder), $grammar->getBindings());

        unset($grammar);

        return new BulkPostgreSqlInsertResult($rows);
    }
}
