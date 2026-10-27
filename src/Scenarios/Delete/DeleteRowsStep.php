<?php

namespace Lapaliv\BulkUpsert\Scenarios\Delete;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Builders\DeleteBuilderFactory;
use Lapaliv\BulkUpsert\Builders\DeleteBulkBuilder;
use Lapaliv\BulkUpsert\Builders\UpdateBulkBuilder;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

/**
 * Runs the query which deletes the rows from the database.
 *
 * @internal
 */
readonly class DeleteRowsStep
{
    /**
     * Create a new step instance.
     *
     * @param \Lapaliv\BulkUpsert\Builders\DeleteBuilderFactory $deleteBuilderFactory
     * @param \Lapaliv\BulkUpsert\Contracts\BulkDriverManager $driverManager
     */
    public function __construct(
        private DeleteBuilderFactory $deleteBuilderFactory,
        private BulkDriverManager $driverManager,
    )
    {
        //
    }

    /**
     * Build the query for the rows that have not been skipped, and run it.
     *
     * Soft deleting is an update of $deletedAtColumn, force deleting is a real delete,
     * so the factory decides which of the two queries has to be built.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $data
     * @param string[] $dateFields
     * @param string|null $deletedAtColumn
     * @param bool $force
     * @param \DateTimeInterface $deletedAt
     *
     * @return bool whether the query has been run
     * @throws \Throwable
     */
    public function handle(
        Model $eloquent,
        BulkAccumulationEntity $data,
        array $dateFields,
        ?string $deletedAtColumn,
        bool $force,
        DateTimeInterface $deletedAt,
    ): bool
    {
        $builder = $this->deleteBuilderFactory->handle(
            $eloquent,
            $data,
            $dateFields,
            $deletedAtColumn,
            $force,
            $deletedAt,
        );
        $driver = $this->driverManager->getForModel($eloquent);

        // The builder is null when every row was skipped by the previous steps.
        if ($builder instanceof UpdateBulkBuilder) {
            $driver->update($eloquent->getConnection(), $builder);
        } elseif ($builder instanceof DeleteBulkBuilder) {
            $driver->forceDelete($eloquent->getConnection(), $builder);
        } else {
            unset($builder, $driver);

            return false;
        }

        unset($builder, $driver);

        // The rows are written now: a failure below must not send them to the database again.
        foreach ($data->getRows() as $row) {
            if (!$row->isSkipDeleting() && $row->getModel()->exists) {
                $row->markWritten();
            }
        }

        return true;
    }
}
