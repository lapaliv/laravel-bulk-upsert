<?php

namespace Lapaliv\BulkUpsert\Features;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Throwable;

class FillWasRecentlyCreatedFeature
{
    /**
     * @param BulkModel $eloquent
     * @param Collection<BulkModel> $collection
     * @param string[] $dateFields
     * @param int|null $lastInsertedId
     * @param CarbonInterface $startedAt
     * @return void
     */
    public function handle(
        BulkModel $eloquent,
        Collection $collection,
        array $dateFields,
        ?int $lastInsertedId,
        CarbonInterface $startedAt,
    ): void {
        if ($lastInsertedId !== null && $eloquent->getIncrementing()) {
            $checker = fn (BulkModel $model) => $this->checkPrimary($model, $lastInsertedId);
        } elseif ($eloquent->usesTimestamps() || array_key_exists($eloquent->getCreatedAtColumn(), $dateFields)) {
            $checker = fn (BulkModel $model) => $this->checkCreatedAt($model, $startedAt);
        } else {
            return;
        }

        $collection->map(
            function (BulkModel $model) use ($checker): void {
                $model->wasRecentlyCreated = $model->wasRecentlyCreated || $checker($model);
            }
        );
    }

    private function checkPrimary(BulkModel $model, int $lastInsertedId): bool
    {
        return is_int($model->getKey()) && $model->getKey() >= $lastInsertedId;
    }

    private function checkCreatedAt(BulkModel $model, CarbonInterface $startedAt): bool
    {
        $createdAt = $model->getAttribute($model->getCreatedAtColumn());

        try {
            if (is_string($createdAt)) {
                $createdAt = Carbon::parse($createdAt);
            }
        } catch (Throwable) {
            // ignoring
        }

        if (empty($createdAt) || !($createdAt instanceof CarbonInterface)) {
            return false;
        }

        return $createdAt->gte($startedAt);
    }
}
