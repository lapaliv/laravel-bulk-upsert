<?php

namespace Lapaliv\BulkUpsert\Traits;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\BulkUpdate;
use Lapaliv\BulkUpsert\Contracts\BulkModel;

trait BulkPreparingBulkUpdateTrait
{
    private function getBulkUpdateInstance(?array $columns = ['*'], ?callable $onSaved = null): BulkUpdate
    {
        return $this->bulkUpdate
            ->onUpdating($this->getOnUpdatingCallback())
            ->onUpdated(
                $this->getSingularListener($this->listeners['afterUpdating'])
            )
            ->onDeleting(
                $this->getSingularListener($this->listeners['beforeDeleting'])
            )
            ->onDeleted(
                $this->getSingularListener($this->listeners['afterDeleting'])
            )
            ->onRestoring(
                $this->getSingularListener($this->listeners['beforeRestoring'])
            )
            ->onRestored(
                $this->getSingularListener($this->listeners['afterRestoring'])
            )
            ->onSaving(
                $this->getSingularListener($this->listeners['beforeSaving'])
            )
            ->onSaved(
                $this->getSavedSingularListener($this->listeners['afterSaving'], $onSaved)
            )
            ->chunk($this->chunkSize)
            ->select($columns)
            ->setEvents($this->events);
    }

    private function getOnUpdatingCallback(): ?Closure
    {
        return function (Collection $collection): Collection {
            $updateOnlyBeforeDeleting = $this->updateAttributes['onlyBeforeDeleting'];
            $updateOnlyBeforeRestoring = $this->updateAttributes['onlyBeforeRestoring'];
            $updateOnlyAnyway = $this->updateAttributes['onlyAnyway'];

            $hasUpdateOnlyBeforeDeleting = empty($updateOnlyBeforeDeleting) === false;
            $hasUpdateOnlyBeforeRestoring = empty($updateOnlyBeforeRestoring) === false;

            $firstModel = $collection->first();
            $deletedAtColumn = method_exists($firstModel, 'getDeletedAtColumn')
                ? $firstModel->getDeletedAtColumn()
                : null;

            /** @var BulkModel $model */
            foreach ($collection as $model) {
                $updateOnly = $updateOnlyAnyway;

                if (($hasUpdateOnlyBeforeDeleting || $hasUpdateOnlyBeforeRestoring)
                    && $deletedAtColumn !== null
                ) {
                    $originalDeletedAt = $model->getOriginal($deletedAtColumn);
                    $actualDeletedAt = $model->getAttribute($deletedAtColumn);

                    if ($originalDeletedAt === null && $actualDeletedAt !== null) {
                        $updateOnly = array_merge($updateOnlyBeforeDeleting, [$deletedAtColumn]);
                    } elseif ($originalDeletedAt !== null && $actualDeletedAt === null) {
                        $updateOnly = array_merge($updateOnlyBeforeRestoring, [$deletedAtColumn]);
                    }
                }

                if (empty($updateOnly)) {
                    continue;
                }

                foreach ($model->getAttributes() as $key => $value) {
                    $originalValue = $model->getOriginal($key);

                    if ($value !== $originalValue && in_array($key, $updateOnly, true) === false) {
                        $model->setAttribute($key, $model->getOriginal($key));
                    }
                }
            }

            foreach ($this->listeners['beforeUpdating'] as $listener) {
                $collection = $listener($collection) ?? $collection;
            }

            return $collection;
        };
    }
}
