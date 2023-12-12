<?php

namespace Lapaliv\BulkUpsert\Drivers\SqLite;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Grammars\SqLiteGrammar;

/**
 * @internal
 */
class SqLiteDriverForceDeleteFeature
{
    public function __construct(
        private MixedValueToSqlConverter $mixedValueToSqlConverter,
    ) {
        //
    }

    public function handle(
        ConnectionInterface $connection,
        DeleteBulkBuilder $builder,
    ): int {
        $grammar = new SqLiteGrammar($this->mixedValueToSqlConverter);

        $result = $connection->delete($grammar->delete($builder), $grammar->getBindings());

        unset($grammar);

        return $result;
    }
}
