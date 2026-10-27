<?php

namespace Lapaliv\BulkUpsert\Scenarios\Upsert;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Scenarios\CreateScenario;
use Lapaliv\BulkUpsert\Support\ReturnedModels;
use Throwable;

/**
 * Creates the rows which are absent in the database and decides which of them still need an update.
 *
 * @internal
 */
readonly class CreateNonexistentRowsStep
{
    /**
     * Create a new step instance.
     *
     * @param \Lapaliv\BulkUpsert\Scenarios\CreateScenario $createScenario
     */
    public function __construct(
        private CreateScenario $createScenario,
    )
    {
        //
    }

    /**
     * Insert the rows that are absent in the database, and split the result.
     *
     * The rows that have really been created are excluded from the following update,
     * and the rest of them are given back to it.
     *
     * The insert is made with the 'ignore' flag on purpose: another process could have
     * created the very same row in between the selection and the insert, and such a row
     * has to be updated rather than to break the whole query.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher $eventDispatcher
     * @param string[] $dateFields
     * @param string[] $selectColumns
     * @param string|null $deletedAtColumn
     *
     * @return list<\Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity> The rows which the insert has sent.
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        array $dateFields,
        array $selectColumns,
        ?string $deletedAtColumn,
        ?ReturnedModels $returned = null,
    ): array
    {
        $nonexistent = $this->collect($data);

        if (!$nonexistent->hasRows()) {
            return [];
        }

        // The rows and their models are taken now: a quiet insert empties the data, and the
        // creating scenario may replace a model with the one it has read back. The kept
        // models hold what the caller asked to write together with the changes of the
        // `saving`/`creating` listeners.
        $rows = $nonexistent->getRows();
        /** @var array<int, \Illuminate\Database\Eloquent\Model> $models */
        $models = [];

        foreach ($rows as $row) {
            $models[spl_object_id($row)] = $row->getModel();
            // The flag must describe this insert only: a stale `true` left by an earlier
            // operation would make the row skip its update. A row whose result stays unknown
            // (a quiet insert) goes to the update, which looks it up first.
            $row->getModel()->wasRecentlyCreated = false;
            $row->setCreatedByInsert(null);
        }

        try {
            $this->createScenario->handle(
                $nonexistent,
                $eventDispatcher,
                ignore: true,
                dateFields: $dateFields,
                selectColumns: $selectColumns,
                deletedAtColumn: $deletedAtColumn,
                returned: $returned,
                // The update below has to know which rows this insert has created, whether or
                // not the result is asked for: otherwise `upsert()` would update a created row
                // with the input once more, while `upsertAndReturn()` would not.
                readBack: true,
                // A row which has appeared meanwhile is skipped and then updated, but any other
                // error of a new row (NOT NULL, CHECK, a foreign key) has to reach the caller.
                duplicatesOnly: true,
            );
        } catch (Throwable $exception) {
            // The insert alone does not complete an upsert: the library cannot tell reliably
            // whether this call has created a row (`wasRecentlyCreated` is a heuristic, and a
            // quiet insert does not compute it). So every row of the insert is retried, with
            // its own model: the retry looks the row up first, so a created row becomes an
            // update, a no-op when nothing has changed. No user code runs here, so the
            // original exception is rethrown as it is.
            foreach ($rows as $row) {
                $row->forgetWritten();
                $row->setModel($models[spl_object_id($row)]);
            }

            throw $exception;
        }

        // The rows which the insert has sent (the cancelled ones were not): each of them has to
        // be either created or found by the update below.
        $sent = [];

        foreach ($rows as $row) {
            if ($row->isWritten()) {
                $sent[] = $row;
            }

            $row->forgetWritten();
        }

        $this->markCreatedRows($data, $models);

        return $sent;
    }

    /**
     * Gather the rows which the previous step has not found in the database.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     *
     * @return \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity
     */
    private function collect(BulkAccumulationEntity $data): BulkAccumulationEntity
    {
        $result = new BulkAccumulationEntity(uniqueBy: $data->getUniqueBy());

        foreach ($data->getRows() as $row) {
            if ($row->isSkipUpdating()) {
                $result->addRow($row);
            }
        }

        return $result;
    }

    /**
     * Define what the update has to do with each of the rows that took part in the insert.
     *
     * A row that has really been created is done with: it is marked as skipped entirely.
     * A row that has not been created means the insert was ignored because the row had
     * appeared in the database in the meantime, so it goes back to the update.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param array<int, \Illuminate\Database\Eloquent\Model> $models The models of the rows before the insert.
     *
     * @return void
     */
    private function markCreatedRows(BulkAccumulationEntity $data, array $models): void
    {
        foreach ($data->getRows() as $row) {
            if (!$row->isSkipUpdating()) {
                continue;
            }

            // A listener has cancelled the creation: the row is not written at all, neither
            // by the insert nor by the update, even if it exists by now.
            if ($row->isSkipCreating()) {
                $row->setSkipSaving(true);

                continue;
            }

            // The driver's answer, recorded before any listener; the public flag (a guess, and
            // changeable by a `created` listener) only when the driver does not know.
            if ($row->isCreatedByInsert() ?? $row->getModel()->wasRecentlyCreated) {
                // This call has created the row, so its upsert is complete.
                $row->markWritten();
                $row->setSkipSaving(true);

                continue;
            }

            $row->setSkipUpdating(false);
            $this->restoreRequestedAttributes($row, $models);
        }
    }

    /**
     * Put the caller's attributes back onto the row that lost the race.
     *
     * Its model is now the row somebody else stored, so without this the update would find
     * nothing dirty and the caller's data would be dropped without a word. The primary key and
     * everything else the database filled in stay as they are — only the attributes that were
     * asked for are written over.
     *
     * Both sides are raw attribute values, so they are merged raw. Feeding them back through
     * `setAttribute()` would run the casts a second time and, for instance, JSON-encode a value
     * that had already been encoded. The original attributes are deliberately left untouched:
     * they hold the stored row, which is what makes the update see the difference.
     *
     * The requested attributes are the raw attributes of the model which the row had before
     * the insert, i.e. the input together with the changes of the `saving`/`creating`
     * listeners.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity $row
     * @param array<int, \Illuminate\Database\Eloquent\Model> $models The models of the rows before the insert.
     *
     * @return void
     */
    private function restoreRequestedAttributes(
        BulkAccumulationItemEntity $row,
        array $models,
    ): void
    {
        $requested = $models[spl_object_id($row)] ?? null;
        $model = $row->getModel();

        // A model which has not been replaced already holds the requested attributes.
        if ($requested === null || $requested === $model) {
            return;
        }

        $model->setRawAttributes(
            array_merge($model->getAttributes(), $requested->getAttributes())
        );
    }
}
