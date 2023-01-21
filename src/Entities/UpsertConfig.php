<?php

namespace Lapaliv\BulkUpsert\Entities;

use Lapaliv\BulkUpsert\Support\BulkCallback;

class UpsertConfig
{
    /**
     * @param string[] $events
     * @param string[] $uniqueAttributes
     * @param string[]|null $updateAttributes
     * @param string[] $selectColumns
     * @param int $chunkSize
     * @param BulkCallback|null $chunkCallback
     * @param BulkCallback|null $creatingCallback
     * @param BulkCallback|null $createdCallback
     * @param BulkCallback|null $updatingCallback
     * @param BulkCallback|null $updatedCallback
     * @param BulkCallback|null $savingCallback
     * @param BulkCallback|null $savedCallback
     */
    public function __construct(
        public array $events,
        public array $uniqueAttributes,
        public ?array $updateAttributes,
        public array $selectColumns,
        public int $chunkSize,
        public ?BulkCallback $chunkCallback,
        public ?BulkCallback $creatingCallback,
        public ?BulkCallback $createdCallback,
        public ?BulkCallback $updatingCallback,
        public ?BulkCallback $updatedCallback,
        public ?BulkCallback $savingCallback,
        public ?BulkCallback $savedCallback,
    )
    {
        // Nothing
    }
}
