<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

class SelectExistingRowsFeature
{
    public function __construct(
        private AddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
    )
    {
        //
    }

    public function handle(
        BulkModel $eloquent,
        Collection $collection,
        array $selectColumns,
        array $uniqueAttributes,
    ): Collection
    {
        $builder = $eloquent->newQuery()
            ->select($selectColumns);

        $this->addWhereClauseToBuilderFeature->handle($builder, $uniqueAttributes, $collection);

        return $builder->get();
    }
}
