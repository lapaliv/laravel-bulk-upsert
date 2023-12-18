<?php

/** @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection */

/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */

namespace Lapaliv\BulkUpsert\Drivers\MySql;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Entities\BulkMySqlInsertResult;
use Lapaliv\BulkUpsert\Grammars\MySqlGrammar;

/**
 * @internal
 */
class MySqlDriverInsertWithResultFeature
{
    public function __construct(
        private MixedValueToSqlConverter $mixedValueToSqlConverter,
    ) {
        //
    }

    public function handle(
        ConnectionInterface $connection,
        InsertBuilder $builder,
        ?string $primaryKeyName,
    ): BulkInsertResult {
        $lastPrimaryBeforeInserting = null;

        if ($primaryKeyName !== null) {
            $lastRow = $connection->selectOne(
                sprintf(
                    'select max(%s) as id from %s',
                    $primaryKeyName,
                    $builder->getInto()
                )
            );

            $lastPrimaryBeforeInserting = $lastRow->id ?? 0;
        }

        $grammar = new MySqlGrammar($this->mixedValueToSqlConverter);

        $connection->insert($grammar->insert($builder), $grammar->getBindings());

        unset($grammar);

        return new BulkMySqlInsertResult(
            is_numeric($lastPrimaryBeforeInserting)
                ? (int) $lastPrimaryBeforeInserting
                : null
        );
    }
}
