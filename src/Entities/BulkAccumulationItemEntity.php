<?php

namespace Lapaliv\BulkUpsert\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * @internal
 */
class BulkAccumulationItemEntity
{
    /**
     * @var mixed
     *
     * @deprecated use `getOriginal()`
     */
    public mixed $row;

    /**
     * @var Model
     *
     * @deprecated use `getModel()`
     */
    public Model $model;

    /**
     * @var bool
     *
     * @deprecated use `isSkipSaving()`
     */
    public bool $skipSaving = false;

    /**
     * @var bool
     *
     * @deprecated use `isCreationSkipped()` and `skipCreation()`
     */
    public bool $skipCreating = false;
    public bool $skipUpdating = false;
    public bool $skipDeleting = false;
    public bool $skipRestoring = false;
    public bool $isDeleting = false;
    public bool $isRestoring = false;

    public function __construct(mixed $original, Model $model)
    {
        $this->row = $original;
        $this->model = $model;
    }

    /**
     * @return mixed
     */
    public function getOriginal(): mixed
    {
        return $this->row;
    }

    /**
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    public function setModel(Model $model): static
    {
        $this->model = $model;

        return $this;
    }
}
