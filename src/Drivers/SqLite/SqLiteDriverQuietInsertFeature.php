<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Drivers\SqLite;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Grammars\SqLiteGrammar;

/**
 * @internal
 */
class SqLiteDriverQuietInsertFeature
{
    public function __construct(
        private MixedValueToSqlConverter $mixedValueToSqlConverter,
    ) {
        //
    }

    public function handle(ConnectionInterface $connection, InsertBuilder $builder): void
    {
        $grammar = new SqLiteGrammar($this->mixedValueToSqlConverter);

        $connection->insert($grammar->insert($builder), $grammar->getBindings());

        unset($grammar);
    }
}
