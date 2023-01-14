<?php

namespace Lapaliv\BulkUpsert\Database\Processors;

use Lapaliv\BulkUpsert\Contracts\BulkDatabaseProcessor;
use Lapaliv\BulkUpsert\Database\Processors\Features\BulkProcessorBuildUpdateFeature;
use Lapaliv\BulkUpsert\Database\Processors\Postgres\BulkPostgresBuildInsertQueryFeature;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderInsert;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderUpdate;

class PostgresProcessor implements BulkDatabaseProcessor
{
    public function __construct(
        private BulkPostgresBuildInsertQueryFeature $insertFeature,
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
