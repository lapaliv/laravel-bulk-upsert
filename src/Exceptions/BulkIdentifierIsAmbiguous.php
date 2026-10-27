<?php

namespace Lapaliv\BulkUpsert\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use LogicException;

/**
 * Thrown when the database finds more than one row equal to the unique attributes of a row.
 *
 * The library does not pick one of them: it would write into an arbitrary row.
 */
class BulkIdentifierIsAmbiguous extends LogicException implements BulkException
{
    /**
     * Create a new exception instance.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string[] $uniqueBy
     *
     * @return void
     */
    public function __construct(private readonly Model $model, private readonly array $uniqueBy)
    {
        parent::__construct(sprintf(
            'More than one stored row is equal to the row by [%s]. Use unique attributes which identify one row.',
            implode(', ', $uniqueBy)
        ));
    }

    /**
     * Get the model whose unique attributes are ambiguous.
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
     * @return array<string, mixed>
     *
     * @psalm-api
     */
    public function context(): array
    {
        return ['unique_by' => $this->uniqueBy];
    }
}
