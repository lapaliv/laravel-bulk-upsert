<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use LogicException;

/**
 * Thrown when a model given to the bulk is bound to another table or connection than the bulk.
 *
 * The bulk writes every row through the table and the connection of its own model, so such a
 * model would be written into the bulk's table: the row with the same key there would change.
 */
class BulkModelDoesNotMatch extends LogicException implements BulkException
{
    /**
     * Create a new exception instance.
     *
     * @param \Illuminate\Database\Eloquent\Model $model The rejected model.
     * @param string $expectedTable
     * @param string|null $expectedConnection
     * @param string $actualTable
     * @param string|null $actualConnection
     *
     * @return void
     */
    public function __construct(
        private readonly Model $model,
        private readonly string $expectedTable,
        private readonly ?string $expectedConnection,
        private readonly string $actualTable,
        private readonly ?string $actualConnection,
    )
    {
        parent::__construct(sprintf(
            'The model of the table "%s" on the connection "%s" cannot be written by the bulk of the table "%s" '
            . 'on the connection "%s". Create a bulk from a model with the same table and connection.',
            $actualTable,
            (string) $actualConnection,
            $expectedTable,
            (string) $expectedConnection,
        ));
    }

    /**
     * Get the rejected model.
     *
     * @return \Illuminate\Database\Eloquent\Model
     *
     * @psalm-api
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get the context of the exception for the logs.
     *
     * @return array<string, string|null>
     *
     * @psalm-api
     */
    public function context(): array
    {
        return [
            'expected_table' => $this->expectedTable,
            'expected_connection' => $this->expectedConnection,
            'actual_table' => $this->actualTable,
            'actual_connection' => $this->actualConnection,
        ];
    }
}
