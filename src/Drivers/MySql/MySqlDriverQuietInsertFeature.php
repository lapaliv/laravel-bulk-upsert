<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Drivers\MySql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Grammars\MySqlGrammar;

/**
 * @internal
 */
class MySqlDriverQuietInsertFeature
{
    public function __construct(
        private MixedValueToSqlConverter $mixedValueToSqlConverter,
    ) {
        //
    }

    public function handle(ConnectionInterface $connection, InsertBuilder $builder): void
    {
        $grammar = new MySqlGrammar($this->mixedValueToSqlConverter);

        $connection->insert($grammar->insert($builder), $grammar->getBindings());

        unset($grammar);
    }
}
