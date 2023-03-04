<?php

namespace Lapaliv\BulkUpsert\Entities;

use Lapaliv\BulkUpsert\Support\BulkCallback;

class BulkScenarioConfig
{
    /**
     * @param string[] $events
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @param string[] $selectColumns
     * @param int $chunkSize
     * @param string[] $dateFields
     * @param string|null $deletedAtColumn
     * @param BulkCallback|null $chunkCallback
     * @param BulkCallback|null $creatingCallback
     * @param BulkCallback|null $createdCallback
     * @param BulkCallback|null $updatingCallback
     * @param BulkCallback|null $updatedCallback
     * @param BulkCallback|null $savingCallback
     * @param BulkCallback|null $savedCallback
     * @param BulkCallback|null $deletingCallback
     * @param BulkCallback|null $deletedCallback
     * @param BulkCallback|null $restoringCallback
     * @param BulkCallback|null $restoredCallback
     */
    public function __construct(
        public array $events = [],
        public array $uniqueAttributes = [],
        public ?array $updateAttributes = null,
        public array $selectColumns = ['*'],
        public int $chunkSize = 100,
        public array $dateFields = [],
        public ?string $deletedAtColumn = null,
        public ?BulkCallback $chunkCallback = null,
        public ?BulkCallback $creatingCallback = null,
        public ?BulkCallback $createdCallback = null,
        public ?BulkCallback $updatingCallback = null,
        public ?BulkCallback $updatedCallback = null,
        public ?BulkCallback $savingCallback = null,
        public ?BulkCallback $savedCallback = null,
        public ?BulkCallback $deletingCallback = null,
        public ?BulkCallback $deletedCallback = null,
        public ?BulkCallback $restoringCallback = null,
        public ?BulkCallback $restoredCallback = null,
    ) {
        // Nothing
    }
}
