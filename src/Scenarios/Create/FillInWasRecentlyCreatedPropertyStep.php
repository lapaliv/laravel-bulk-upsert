<?php

namespace Lapaliv\BulkUpsert\Scenarios\Create;

use Carbon\Carbon;
use DateTimeInterface;
use Lapaliv\BulkUpsert\Contracts\BulkInsertResult;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

/**
 * Defines which of the models have really been created by the insert query.
 *
 * @internal
 */
class FillInWasRecentlyCreatedPropertyStep
{
    /**
     * Define the value of the property 'wasRecentlyCreated' for each model.
     *
     * There are two ways to do it:
     *
     * - If the model has an incrementing primary key, then $insertResult keeps the last
     *   identifier in the database before inserting. If the model has a greater identifier,
     *   then it was recently created.
     * - If the value of the attribute 'created_at' is greater than $startedAt
     *   (the date before inserting), then the model was recently created.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\Contracts\BulkInsertResult $insertResult
     * @param string[] $dataFields
     * @param \DateTimeInterface $startedAt
     *
     * @return void
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkInsertResult $insertResult,
        array $dataFields,
        DateTimeInterface $startedAt,
    ): void
    {
        $eloquent = $data->getFirstModel();
        $createdAtColumn = $eloquent->getCreatedAtColumn();

        // A driver which returns the inserted rows (PostgreSQL) has matched exactly them.
        if ($insertResult->getRows() !== null) {
            foreach ($data->getRows() as $row) {
                $row->setCreatedByInsert($row->isCreatedByInsert() ?? true);
            }
        }

        // What the driver knows is the answer; the guesses below are only for the rest.
        $unknown = [];

        foreach ($data->getRows() as $index => $row) {
            $known = $row->isCreatedByInsert();

            if ($known === null) {
                $unknown[$index] = $row;
            } else {
                $row->getModel()->wasRecentlyCreated = $known;
            }
        }

        if ($eloquent->getIncrementing() && $insertResult->getMaxPrimaryBeforeInserting() !== null) {
            foreach ($unknown as $row) {
                $row->getModel()->wasRecentlyCreated = $row->getModel()->wasRecentlyCreated
                    || $row->getModel()->getKey() > $insertResult->getMaxPrimaryBeforeInserting();
            }
        } elseif ($eloquent->usesTimestamps() && $createdAtColumn !== null) {
            $format = $dataFields[$createdAtColumn] ?? 'Y-m-d H:i:s';

            foreach ($unknown as $row) {
                if ($row->getModel()->wasRecentlyCreated) {
                    continue;
                }

                /** @psalm-suppress MixedAssignment */
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
