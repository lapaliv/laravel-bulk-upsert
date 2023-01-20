<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\MassAssignmentException;

/**
 * @property bool $wasRecentlyCreated
 * @property bool $exists
 */
interface BulkModel
{
    /**
     * Get the event dispatcher instance.
     *
     * @return Dispatcher
     */
    public static function getEventDispatcher();

    /**
     * Get an attribute from the model.
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key);

    /**
     * Get all of the current attributes on the model.
     *
     * @return array
     */
    public function getAttributes();

    /**
     * Set a given attribute on the model.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return mixed
     */
    public function setAttribute($key, $value);

    /**
     * Create a new Eloquent Collection instance.
     *
     * @param BulkModel[] $models
     *
     * @return Collection
     */
    public function newCollection(array $models = []);

    /**
     * Fire the given event for the model.
     *
     * @param string $event
     * @param bool $halt
     *
     * @return mixed
     */
    public function fireModelEvent($event, $halt = true);

    /**
     * Determine if the model uses timestamps.
     *
     * @return bool
     */
    public function usesTimestamps();

    /**
     * Get the name of the "created at" column.
     *
     * @return string|null
     */
    public function getCreatedAtColumn();

    /**
     * Get the name of the "updated at" column.
     *
     * @return string|null
     */
    public function getUpdatedAtColumn();

    /**
     * Get a new query builder for the model's table.
     *
     * @return Builder
     */
    public function newQuery();

    /**
     * Get the database connection for the model.
     *
     * @return Connection
     */
    public function getConnection();

    /**
     * Get the attributes that should be converted to dates.
     *
     * @return string[]
     */
    public function getDates();

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function getDateFormat();

    /**
     * Get the casts array.
     *
     * @return array<string, mixed>
     */
    public function getCasts();

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName();

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey();

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getIncrementing();

    /**
     * Fill the model with an array of attributes.
     *
     * @param array<string, mixed> $attributes
     *
     * @return $this
     * @throws MassAssignmentException
     *
     */
    public function fill(array $attributes);

    /**
     * Set the array of model attributes. No checking is done.
     *
     * @param array $attributes
     * @param bool $sync
     * @return $this
     */
    public function setRawAttributes(array $attributes, $sync = false);

    /**
     * Sync the original attributes with the current.
     *
     * @return $this
     */
    public function syncOriginal();

    /**
     * Get the attributes that have been changed since the last sync.
     *
     * @return array
     */
    public function getDirty();

    /**
     * Determine if the model or any of the given attribute(s) have been modified.
     *
     * @param array|string|null $attributes
     * @return bool
     */
    public function isDirty($attributes = null);

    /**
     * Sync the changed attributes.
     *
     * @return $this
     */
    public function syncChanges();

    /**
     * Get the relationships that are touched on save.
     *
     * @return array
     */
    public function getTouchedRelations();

    /**
     * Touch the owning relations of the model.
     *
     * @return void
     */
    public function touchOwners();

    /**
     * Set the value of the "created at" attribute.
     *
     * @param mixed $value
     * @return $this
     */
    public function setCreatedAt($value);

    /**
     * Set the value of the "updated at" attribute.
     *
     * @param mixed $value
     * @return $this
     */
    public function setUpdatedAt($value);

    /**
     * Update the creation and update timestamps.
     *
     * @return $this
     */
    public function updateTimestamps();

    /**
     * Get all the loaded relations for the instance.
     *
     * @return array
     */
    public function getRelations();

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable();
}
