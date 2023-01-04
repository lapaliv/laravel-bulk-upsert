<?php

namespace Lapaliv\BulkUpsert\DatabaseDrivers\Mysql;

use Illuminate\Database\Eloquent\Builder;
use Lapaliv\BulkUpsert\Features\BulkAddWhereClauseToBuilderFeature;

class BulkMysqlDriverSelectAffectedRowsFeature
{
    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param string[] $uniqueAttributes
     */
    public function __construct(
        protected Builder $builder,
        protected array   $uniqueAttributes,
    )
    {
        //
    }

    /**
     * @param array<int, array<string, scalar>> $rows
     * @param string[] $columns
     * @return \stdClass[]
     */
    public function handle(array $rows, array $columns): array
    {
        $builder = $this->builder->select($columns);

        (new BulkAddWhereClauseToBuilderFeature($this->uniqueAttributes))
            ->handle($builder, $rows);

        return $builder->get()->toArray();
    }
}