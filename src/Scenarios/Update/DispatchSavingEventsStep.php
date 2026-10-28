<?php

namespace Lapaliv\BulkUpsert\Scenarios\Update;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Collections\BulkRowCollection;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkRow;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;

/**
 * Dispatches the 'saving' events before the rows are updated.
 *
 * @internal
 */
class DispatchSavingEventsStep
{
    /**
     * Dispatch the 'saving' and 'savingMany' events for provided $data->getRows().
     *
     * Unlike the creating flow, a cancelled row is not removed from $data: the row keeps
     * participating in the algorithm, but it is marked as skipped so that neither the update
     * query nor the following events touch it.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     *
     * @return void
     */
    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
    ): void
    {
        if (!$eventDispatcher->hasListeners(BulkEventEnum::saving())) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $models */
        $models = $eloquent->newCollection();
        /** @var \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $bulkRows */
        $bulkRows = new BulkRowCollection();

        // The rows whose "nothing to update" is decided here, after all the saving listeners:
        // a row skipped for another reason before this step keeps that decision.
        /** @var list<\Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity> $decided */
        $decided = [];

        // Firstly, it needs to fire the 'saving' event for each row.
        // If the event dispatcher returns false, the row won't be saved at all.
        foreach ($data->getRows() as $row) {
            // A row which is not saved here (not found, or created by the upsert already) gets
            // no saving event.
            if ($row->isSkipSaving()) {
                continue;
            }

            if ($eventDispatcher->dispatch(BulkEventEnum::Saving, $row->getModel()) === false) {
                $row->setSkipSaving(true);

                continue;
            }

            if (!$row->isSkipUpdating()) {
                $decided[] = $row;
            }

            $models->push($row->getModel());
            $bulkRows->push(
                new BulkRow($row->getModel(), $row->getOriginal(), $data->getUniqueBy())
            );
        }

        // Secondly, it needs to fire the 'savingMany' event for all the rows.
        // If the event dispatcher returns false, none of the rows will be saved.
        if ($models->isNotEmpty()) {
            $this->processAllRows($data, $eventDispatcher, $models, $bulkRows);
        }

        // Only now, after `saving` and `savingMany`, it is known whether a row has anything to
        // update: either listener may change a clean model or revert all the changes.
        foreach ($decided as $row) {
            if (!$row->isSkipSaving()) {
                $row->setSkipUpdating(!$row->getModel()->isDirty());
            }
        }

        unset($models, $bulkRows, $decided);
    }

    /**
     * Fire the 'savingMany' event for all the rows.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model> $models
     * @param \Lapaliv\BulkUpsert\Collections\BulkRowCollection<\Illuminate\Database\Eloquent\Model, mixed> $bulkRows
     *
     * @return void
     */
    private function processAllRows(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        Collection $models,
        BulkRowCollection $bulkRows,
    ): void
    {
        /** @psalm-suppress MixedAssignment */
        $dispatchingResult = $eventDispatcher->dispatch(BulkEventEnum::SavingMany, $models, $bulkRows);

        if ($dispatchingResult === false) {
            foreach ($data->getRows() as $row) {
                $row->setSkipSaving(true);
            }
        }
    }
}
