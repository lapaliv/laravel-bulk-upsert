<?php

namespace Lapaliv\BulkUpsert\Scenarios\Update;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Builders\UpdateBuilderFactory;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Support\ModelState;

/**
 * Runs the query which updates the rows in the database.
 *
 * @internal
 */
readonly class UpdateRowsStep
{
    /**
     * Create a new step instance.
     *
     * @param \Lapaliv\BulkUpsert\Builders\UpdateBuilderFactory $updateBuilderFactory
     * @param \Lapaliv\BulkUpsert\Contracts\BulkDriverManager $driverManager
     */
    public function __construct(
        private UpdateBuilderFactory $updateBuilderFactory,
        private BulkDriverManager $driverManager,
    )
    {
        //
    }

    /**
     * Build the update query for the rows that have not been skipped, and run it.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param string[] $dateFields
     * @param string|null $deletedAtColumn
     *
     * @return void
     */
    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        array $dateFields,
        ?string $deletedAtColumn,
    ): void
    {
        // The listeners have run: the statement is built from `$eloquent` for these rows.
        $data->assertTarget([$eloquent, ...$data->getNotSkippedUpdatingModels()]);

        $builder = $this->updateBuilderFactory->handle($eloquent, $data, $dateFields, $deletedAtColumn);
        $driver = $this->driverManager->getForModel($eloquent);

        // The builder is null when every row was either skipped
        // or has nothing to update at all.
        // The builder is null when every row was either skipped or has nothing to update.
        if ($builder !== null) {
            $driver->update($eloquent->getConnection(), $builder);
        }

        // Only now, when the statement has run, the planned values become the written ones.
        // A row which takes part but has nothing to write gets its key only.
        $keyName = $eloquent->getKeyName();

        // Every row of the statement is marked first: the statement has updated all of them,
        // so none of them may be retried even if syncing a model below throws.
        foreach ($data->getRows() as $row) {
            $planned = $row->getPlannedUpdate();

            if ($planned !== null) {
                $row->setWrittenUpdate($planned);
                $row->markWritten();
            }
        }

        foreach ($data->getRows() as $row) {
            $planned = $row->getWrittenUpdate();

            if ($planned !== null) {
                $this->keepStoredKey($row->getModel(), $keyName, $planned[$keyName] ?? null);
            }
        }
    }

    /**
     * Make the model describe the row which the statement has written.
     *
     * The primary key is never written, so a changed key goes back to the stored one, and a
     * model given without its original attributes gets the stored key as the original one.
     * The raw attribute is set directly, so no mutator runs and the cast caches are kept.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $keyName
     * @param mixed $storedKey
     *
     * @return void
     */
    private function keepStoredKey(Model $model, string $keyName, mixed $storedKey): void
    {
        if ($keyName === '' || $storedKey === null) {
            return;
        }

        /** @psalm-suppress MixedAssignment */
        $current = $model->getAttributes()[$keyName] ?? null;
        /** @psalm-suppress MixedAssignment */
        $original = $model->getRawOriginal($keyName);

        if ($current !== null && (string)$current === (string)$storedKey && $original !== null) {
            return;
        }

        // The raw value is set directly: no mutator runs and the cast caches are kept.
        ModelState::setStored($model, $keyName, $storedKey);
    }
}
