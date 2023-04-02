<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

/**
 * @internal
 */
class SelectExistingRowsFeature
{
    public function __construct(
        private AddWhereClauseToBuilderFeature $addWhereClauseToBuilderFeature,
    ) {
        //
    }

    public function handle(
        BulkModel $eloquent,
        BulkAccumulationEntity $data,
        array $selectColumns,
        ?string $deletedAtColumn = null,
    ): Collection {
        $collection = $data->getNotSkippedModels();

        if ($collection->isEmpty()) {
            return $eloquent->newCollection();
        }

        $builder = $eloquent->newQuery()
            ->select($selectColumns)
            ->limit($collection->count());

        if ($deletedAtColumn !== null) {
            /** @phpstan-ignore-next-line */
            $builder->withTrashed();
        }

        $this->addWhereClauseToBuilderFeature->handle($builder, $data->uniqueBy, $collection);

        $result = $builder->get();

        if ($result instanceof Collection) {
            return $result;
        }

        return new Collection($result->all());
    }
}
