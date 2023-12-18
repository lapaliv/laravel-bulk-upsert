<?php

namespace Lapaliv\BulkUpsert\Drivers\SqLite;

use Illuminate\Database\ConnectionInterface;
use Lapaliv\BulkUpsert\Builders\InsertBuilder;
use Lapaliv\BulkUpsert\Converters\MixedValueToSqlConverter;
use Lapaliv\BulkUpsert\Entities\BulkSqLiteInsertResult;
use Lapaliv\BulkUpsert\Grammars\SqLiteGrammar;

class SqLiteDriverInsertWithResultFeature
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
    ): BulkSqLiteInsertResult {
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

        $grammar = new SqLiteGrammar($this->mixedValueToSqlConverter);
        $sql = $grammar->insert($builder);
        $bindings = $grammar->getBindings();
        $connection->insert($sql, $bindings);

        unset($grammar);

        return new BulkSqLiteInsertResult(
            is_numeric($lastPrimaryBeforeInserting)
                ? (int) $lastPrimaryBeforeInserting
                : null
        );
    }
}
