<?php

namespace Lapaliv\BulkUpsert\Database\Drivers\Mysql;

use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Features\BulkAddWhereClauseToBuilderFeature;
use stdClass;

class BulkMysqlDriverSelectAffectedRowsFeature
{
    public function __construct(
        private BulkAddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
    )
    {
        //
    }

    /**
     * @param array<int, array<string, scalar>> $rows
     * @param string[] $columns
     * @return stdClass[]
     */
    public function handle(
        Builder $builder,
        array $uniqueAttributes,
        array $rows,
        array $columns
    ): array
    {
        $builder->select($columns);

        $this->addWhereClauseToBuilderFeature->handle($builder, $uniqueAttributes, $rows);

        return $builder->get()->toArray();
    }
}
