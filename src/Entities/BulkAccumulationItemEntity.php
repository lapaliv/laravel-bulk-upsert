<?php

namespace Lapaliv\BulkUpsert\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * One accumulated row: the value which the user gave and its model.
 *
 * The flags of the entity say which parts of the process the row must skip.
 * The listeners of the events change them while the scenario is running.
 *
 * @internal
 */
class BulkAccumulationItemEntity
{
    /**
     * The value which was given to the bulk instance.
     *
     * @var mixed
     */
    private mixed $row;

    /**
     * The model which was built from the given value.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    private Model $model;

    /**
     * Whether the row must be skipped by the saving process.
     *
     * @var bool
     */
    private bool $skipSaving = false;

    /**
     * Whether the row must be skipped by the creating process.
     *
     * @var bool
     */
    private bool $skipCreating = false;

    /**
     * Whether the row must be skipped by the updating process.
     *
     * @var bool
     */
    private bool $skipUpdating = false;

    /**
     * Whether the row must be skipped by the deleting process.
     *
     * @var bool
     */
    private bool $skipDeleting = false;

    /**
     * Whether the row must be skipped by the restoring process.
     *
     * @var bool
     */
    private bool $skipRestoring = false;

    /**
     * Whether the row is being soft deleted by the current query.
     *
     * @var bool
     */
    private bool $deleting = false;

    /**
     * Whether the row is being restored by the current query.
     *
     * @var bool
     */
    private bool $restoring = false;

    /**
     * Create a new accumulated row.
     *
     * @param mixed $original
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return void
     */
    public function __construct(mixed $original, Model $model)
    {
        $this->row = $original;
        $this->model = $model;
    }

    /**
     * Get the value which was given to the bulk instance.
     *
     * @return mixed
     */
    public function getOriginal(): mixed
    {
        return $this->row;
    }

    /**
     * Get the model of the row.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Set the model of the row.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return $this
     */
    public function setModel(Model $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Determine whether the row must be skipped by the saving process.
     *
     * @return bool
     */
    public function isSkipSaving(): bool
    {
        return $this->skipSaving;
    }

    /**
     * Set whether the row must be skipped by the saving process.
     *
     * @param bool $skipSaving
     *
     * @return void
     */
    public function setSkipSaving(bool $skipSaving): void
    {
        $this->skipSaving = $skipSaving;
    }

    /**
     * Determine whether the row must be skipped by the creating process.
     *
     * @return bool
     */
    public function isSkipCreating(): bool
    {
        return $this->skipCreating;
    }

    /**
     * Determine whether the row must be skipped by the updating process.
     *
     * @return bool
     */
    public function isSkipUpdating(): bool
    {
        return $this->skipUpdating;
    }

    /**
     * Set whether the row must be skipped by the updating process.
     *
     * @param bool $skipUpdating
     *
     * @return void
     */
    public function setSkipUpdating(bool $skipUpdating): void
    {
        $this->skipUpdating = $skipUpdating;
    }

    /**
     * Determine whether the row must be skipped by the deleting process.
     *
     * @return bool
     */
    public function isSkipDeleting(): bool
    {
        return $this->skipDeleting;
    }

    /**
     * Set whether the row must be skipped by the deleting process.
     *
     * @param bool $skipDeleting
     *
     * @return void
     */
    public function setSkipDeleting(bool $skipDeleting): void
    {
        $this->skipDeleting = $skipDeleting;
    }

    /**
     * Determine whether the row must be skipped by the restoring process.
     *
     * @return bool
     */
    public function isSkipRestoring(): bool
    {
        return $this->skipRestoring;
    }

    /**
     * Set whether the row must be skipped by the restoring process.
     *
     * @param bool $skipRestoring
     *
     * @return void
     */
    public function setSkipRestoring(bool $skipRestoring): void
    {
        $this->skipRestoring = $skipRestoring;
    }

    /**
     * Determine whether the row is being soft deleted by the current query.
     *
     * @return bool
     */
    public function isDeleting(): bool
    {
        return $this->deleting;
    }

    /**
     * Set whether the row is being soft deleted by the current query.
     *
     * @param bool $deleting
     *
     * @return void
     */
    public function setDeleting(bool $deleting): void
    {
        $this->deleting = $deleting;
    }

    /**
     * Determine whether the row is being restored by the current query.
     *
     * @return bool
     */
    public function isRestoring(): bool
    {
        return $this->restoring;
    }

    /**
     * Set whether the row is being restored by the current query.
     *
     * @param bool $restoring
     *
     * @return void
     */
    public function setRestoring(bool $restoring): void
    {
        $this->restoring = $restoring;
    }
}
