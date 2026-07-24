<?php

namespace Lapaliv\BulkUpsert\Scenarios\Update;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Builders\UpdateBuilderFactory;
use Lapaliv\BulkUpsert\Contracts\BulkDriverManager;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;

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
        $builder = $this->updateBuilderFactory->handle($eloquent, $data, $dateFields, $deletedAtColumn);
        $driver = $this->driverManager->getForModel($eloquent);

        // The builder is null when every row was either skipped
        // or has nothing to update at all.
        if ($builder !== null) {
            $driver->update($eloquent->getConnection(), $builder);
        }
    }
}
