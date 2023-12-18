<?php

namespace Lapaliv\BulkUpsert\Drivers\MySql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Grammars\MySqlGrammar;

/**
 * @internal
 */
class MySqlDriverUpdateFeature
{
    public function __construct(private MixedValueToSqlConverter $mixedValueToSqlConverter)
    {
        //
    }

    public function handle(
        ConnectionInterface $connection,
        UpdateBulkBuilder $builder,
    ): int {
        $grammar = new MySqlGrammar($this->mixedValueToSqlConverter);

        $result = $connection->update($grammar->update($builder), $grammar->getBindings());

        unset($grammar);

        return $result;
    }
}
