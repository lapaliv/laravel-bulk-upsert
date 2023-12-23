<?php

namespace Lapaliv\BulkUpsert\Features;

use Carbon\Carbon;
use DateTimeInterface;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

class FillInWasRecentlyCreatedPropertyFeature
{
    /**
     * Define the value of the property 'wasRecentlyCreated' for each model.
     * There are two ways to do it:
     * 1. If the model has an incrementing primary key,
     *    then $insertResult will keep the last identifier in
     *    the database before inserting. If the model has a greater identifier,
     *    then it was recently created.
     * 2. If the value of the attribute 'created_at' is greater than
     *    $startedAt (the date before inserting), then the model was
     *    recently created.
     *
     * @param BulkAccumulationEntity $data
     * @param BulkInsertResult $insertResult
     * @param array $dataFields
     * @param DateTimeInterface $startedAt
     *
     * @return void
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkInsertResult $insertResult,
        array $dataFields,
        DateTimeInterface $startedAt,
    ): void {
        $eloquent = $data->getFirstModel();

        if ($eloquent->getIncrementing() && $insertResult->getMaxPrimaryBeforeInserting() !== null) {
            foreach ($data->getRows() as $row) {
                $row->getModel()->wasRecentlyCreated = $row->getModel()->wasRecentlyCreated
                    || $row->getModel()->getKey() > $insertResult->getMaxPrimaryBeforeInserting();
            }
        } elseif ($eloquent->usesTimestamps() && $eloquent->getCreatedAtColumn()) {
            $createdAtColumn = $eloquent->getCreatedAtColumn();
            $format = $dataFields[$eloquent->getCreatedAtColumn()] ?? 'Y-m-d H:i:s';

            foreach ($data->getRows() as $row) {
                if ($row->getModel()->wasRecentlyCreated) {
                    continue;
                }

                $createdAt = $row->getModel()->getAttribute($createdAtColumn);

                if (is_string($createdAt)) {
                    $createdAt = Carbon::parse($createdAt);
                }

                if ($createdAt instanceof DateTimeInterface) {
                    $row->getModel()->wasRecentlyCreated = $startedAt->format($format) <= $createdAt->format($format);
                }
            }
        }
    }
}
