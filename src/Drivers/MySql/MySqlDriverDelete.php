<?php

namespace Lapaliv\BulkUpsert\Drivers\MySql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Grammars\MySqlGrammar;

/**
 * @internal
 */
class MySqlDriverDelete
{
    public function __construct(private MixedValueToSqlConverter $mixedValueToSqlConverter)
    {
        //
    }

    public function handle(
        ConnectionInterface $connection,
        DeleteBulkBuilder $builder,
    ): int {
        $grammar = new MySqlGrammar($this->mixedValueToSqlConverter);

        $result = $connection->delete($grammar->delete($builder), $grammar->getBindings());

        unset($grammar);

        return $result;
    }
}
