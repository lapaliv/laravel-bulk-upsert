<?php

namespace Lapaliv\BulkUpsert\Database\Processors;

use Lapaliv\BulkUpsert\Contracts\BulkDatabaseProcessor;
use Lapaliv\BulkUpsert\Database\Processors\Features\BulkProcessorBuildUpdateFeature;
use Lapaliv\BulkUpsert\Database\Processors\Mysql\BulkMysqlBuildInsertQueryFeature;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderInsert;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderUpdate;

class MysqlProcessor implements BulkDatabaseProcessor
{
    public function __construct(
        private BulkMysqlBuildInsertQueryFeature $insertFeature,
        private BulkProcessorBuildUpdateFeature $updateFeature,
    )
    {
        //
    }

    public function insert(BulkSqlBuilderInsert $builder): array
    {
        return $this->insertFeature->handle($builder);
    }


    public function update(BulkSqlBuilderUpdate $builder): array
    {
        return $this->updateFeature->handle($builder);
    }
}
