<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

/**
 * @internal
 */
class MatchSelectedModelsFeature
{
    public function __construct(
        private GetUniqueKeyFeature $getUniqueKeyFeature,
    ) {
        //
    }

    /**
     * Compare rows from $data with rows from the database,
     * and replace the former with the latter.
     *
     * @param BulkAccumulationEntity $data
     * @param Collection<int|string, Model> $existingRows
     *
     * @return void
     */
    public function handle(BulkAccumulationEntity $data, Collection $existingRows): void
    {
        $keyedExistingRows = $existingRows->keyBy(
            function (Model $model) use ($data): string {
                return $this->getUniqueKeyFeature->handle($model, $data->getUniqueBy());
            }
        );

        foreach ($data->getRows() as $row) {
            $key = $this->getUniqueKeyFeature->handle($row->getModel(), $data->getUniqueBy());

            if ($keyedExistingRows->has($key)) {
                $row->setModel($keyedExistingRows->get($key));
            } else {
                $data->unsetRow($key);
            }
        }
    }
}
