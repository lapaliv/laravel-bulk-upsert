<?php

namespace Lapaliv\BulkUpsert\Database\Processors;

use Lapaliv\BulkUpsert\Contracts\BulkDatabaseProcessor;
use Lapaliv\BulkUpsert\Database\Processors\Features\ProcessorBuildUpdateFeature;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Operations\BulkSqlBuilderUpdateOperation;

class PostgresProcessor implements BulkDatabaseProcessor
{
    public function __construct(
        private ProcessorBuildUpdateFeature $updateFeature,
    )
    {
        //
    }


    public function update(BulkSqlBuilderUpdateOperation $builder): array
    {
        return $this->updateFeature->handle($builder);
    }
}
