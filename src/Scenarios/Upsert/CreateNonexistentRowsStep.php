<?php

namespace Lapaliv\BulkUpsert\Scenarios\Upsert;

use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Scenarios\CreateScenario;

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
     * @return void
     */
    public function handle(
        BulkAccumulationEntity $data,
        BulkEventDispatcher $eventDispatcher,
        array $dateFields,
        array $selectColumns,
        ?string $deletedAtColumn,
    ): void
    {
        $nonexistent = $this->collect($data);

        if (!$nonexistent->hasRows()) {
            return;
        }

        // The creating scenario may replace a row's model with the one it read back from the
        // database, which drops whatever the caller asked to write. Remember the attributes
        // now, while the models still hold them.
        $requestedAttributes = $this->rememberRequestedAttributes($nonexistent);

        $this->createScenario->handle(
            $nonexistent,
            $eventDispatcher,
            ignore: true,
            dateFields: $dateFields,
            selectColumns: $selectColumns,
            deletedAtColumn: $deletedAtColumn,
        );

        $this->markCreatedRows($data, $requestedAttributes);
    }

    /**
     * Snapshot the attributes the caller asked to write, keyed by the row they belong to.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     *
     * @return array<int, array<string, mixed>>
     */
    private function rememberRequestedAttributes(BulkAccumulationEntity $data): array
    {
        $result = [];

        foreach ($data->getRows() as $row) {
            $result[spl_object_id($row)] = $row->getModel()->getAttributes();
        }

        return $result;
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
     * @param array<int, array<string, mixed>> $requestedAttributes
     *
     * @return void
     */
    private function markCreatedRows(BulkAccumulationEntity $data, array $requestedAttributes): void
    {
        foreach ($data->getRows() as $row) {
            if (!$row->isSkipUpdating()) {
                continue;
            }

            if ($row->getModel()->wasRecentlyCreated) {
                $row->setSkipSaving(true);

                continue;
            }

            $row->setSkipUpdating(false);
            $this->restoreRequestedAttributes($row, $requestedAttributes);
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
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity $row
     * @param array<int, array<string, mixed>> $requestedAttributes
     *
     * @return void
     */
    private function restoreRequestedAttributes(
        BulkAccumulationItemEntity $row,
        array $requestedAttributes,
    ): void
    {
        $requested = $requestedAttributes[spl_object_id($row)] ?? [];

        if (empty($requested)) {
            return;
        }

        $model = $row->getModel();
        $model->setRawAttributes(
            array_merge($model->getAttributes(), $requested)
        );
    }
}
