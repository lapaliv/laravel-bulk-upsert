<?php

namespace Lapaliv\BulkUpsert;

use Closure;
use Generator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Contracts\BulkException;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Exceptions\BulkBadMethodCall;
use Lapaliv\BulkUpsert\Exceptions\BulkBindingResolution;
use Lapaliv\BulkUpsert\Exceptions\BulkIdentifierDidNotFind;
use Lapaliv\BulkUpsert\Exceptions\BulkTransmittedClassIsNotAModel;
use Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported;
use Lapaliv\BulkUpsert\Features\GetDateFieldsFeature;
use Lapaliv\BulkUpsert\Features\GetDeletedAtColumnFeature;
use Lapaliv\BulkUpsert\Scenarios\CreateScenario;
use Lapaliv\BulkUpsert\Scenarios\DeleteScenario;
use Lapaliv\BulkUpsert\Scenarios\UpdateScenario;
use Lapaliv\BulkUpsert\Scenarios\UpsertScenario;
use stdClass;

/**
 * @template TCollection of Collection
 * @template TModel of Model
 *
 * @method $this onCreating(callable|null $callback)
 * @method $this onCreated(callable|null $callback)
 * @method $this onCreatingMany(callable|null $callback)
 * @method $this onCreatedMany(callable|null $callback)
 * @method $this onUpdating(callable|null $callback)
 * @method $this onUpdated(callable|null $callback)
 * @method $this onUpdatingMany(callable|null $callback)
 * @method $this onUpdatedMany(callable|null $callback)
 * @method $this onSaving(callable|null $callback)
 * @method $this onSaved(callable|null $callback)
 * @method $this onSavingMany(callable|null $callback)
 * @method $this onSavedMany(callable|null $callback)
 * @method $this onDeleting(callable|null $callback)
 * @method $this onDeleted(callable|null $callback)
 * @method $this onDeletingMany(callable|null $callback)
 * @method $this onDeletedMany(callable|null $callback)
 * @method $this onRestoring(callable|null $callback)
 * @method $this onRestored(callable|null $callback)
 * @method $this onRestoringMany(callable|null $callback)
 * @method $this onRestoredMany(callable|null $callback)
 */
class Bulk
{
    private const DEFAULT_CHUNK_SIZE = 100;

    /**
     * @var Model|TModel
     */
    private Model $model;
    private int $chunkSize = self::DEFAULT_CHUNK_SIZE;
    /**
     * @var array<int, callable|string[]>
     */
    private array $uniqueBy = [];
    private BulkEventDispatcher $eventDispatcher;
    private ?string $deletedAtColumn;

    /**
     * @var array<string, string>
     */
    private array $dateFields;

    /**
     * @var array{
     *  createOrIgnore: array<string, BulkAccumulationEntity>,
     *  create: array<string, BulkAccumulationEntity>,
     *  update: array<string, BulkAccumulationEntity>,
     *  upsert: array<string, BulkAccumulationEntity>,
     * }
     */
    private array $storage = [
        'createOrIgnore' => [],
        'create' => [],
        'update' => [],
        'upsert' => [],
        'delete' => [],
        'forceDelete' => [],
    ];

    /**
     * @var string[]
     */
    private array $updateOnly = [];

    /**
     * @var string[]
     */
    private array $updateExcept = [];
    private bool $withTrashed = false;

    /**
     * @param class-string<TModel>|TModel $model
     *
     * @throws BulkException
     */
    public function __construct(Model|string $model)
    {
        if (is_string($model) && class_exists($model)) {
            try {
                $model = Container::getInstance()->make($model);
            } catch (BindingResolutionException $exception) {
                throw new BulkBindingResolution(
                    $exception->getMessage(),
                    $exception->getCode(),
                    $exception
                );
            }
        }

        if ($model instanceof Model) {
            $this->model = $model;
        } else {
            throw new BulkTransmittedClassIsNotAModel(
                is_object($model) ? get_class($model) : (string) $model
            );
        }

        $this->uniqueBy([$this->model->getKeyName()]);
    }

    public function __call(string $name, array $arguments)
    {
        if (str_starts_with($name, 'on')) {
            $event = lcfirst(substr($name, 2));

            if (in_array($event, BulkEventEnum::cases())) {
                $this->getEventDispatcher()->listen($event, $arguments[0] ?? null);

                return $this;
            }
        }

        throw new BulkBadMethodCall(static::class, $name);
    }

    /**
     * Sets the chunk size.
     *
     * @param int $size
     *
     * @return $this
     */
    public function chunk(int $size = self::DEFAULT_CHUNK_SIZE): static
    {
        $this->chunkSize = $size;

        return $this;
    }

    /**
     * Defines the unique attributes of the rows.
     *
     * @param callable|string|string[]|string[][] $attributes
     *
     * @return $this
     */
    public function uniqueBy(string|array|callable $attributes): static
    {
        if (is_callable($attributes)) {
            $this->uniqueBy = [Closure::fromCallable($attributes)];

            return $this;
        }

        if (is_string($attributes)) {
            $this->uniqueBy = [[$attributes]];

            return $this;
        }

        $attributes = array_values($attributes);

        if (is_array($attributes[0])) {
            $this->uniqueBy = $attributes;
        } else {
            $this->uniqueBy = [$attributes];
        }

        return $this;
    }

    /**
     * Defines the alternatives of the unique attributes.
     *
     * @param callable|string|string[]|string[][] $attributes
     *
     * @return $this
     */
    public function orUniqueBy(string|array|callable $attributes): static
    {
        if (is_callable($attributes)) {
            $this->uniqueBy = [Closure::fromCallable($attributes)];

            return $this;
        }

        if (is_string($attributes)) {
            $this->uniqueBy[] = [$attributes];

            return $this;
        }

        $attributes = array_values($attributes);

        if (is_array($attributes[0])) {
            $this->uniqueBy = array_merge($this->uniqueBy, $attributes);
        } else {
            $this->uniqueBy[] = $attributes;
        }

        return $this;
    }

    /**
     * Sets enabled events.
     *
     * @param string[] $events
     *
     * @return $this
     */
    public function setEvents(array $events): static
    {
        $this->getEventDispatcher()->restrict($events);

        return $this;
    }

    /**
     * Disables the next events:
     * - `saved`,
     * - `created`,
     * - `updated`,
     * - `deleted`,
     * - `restored`.
     *
     * @return $this
     */
    public function disableModelEndEvents(): static
    {
        $enabledEvents = $this->getEventDispatcher()->getEnabledEvents() ?? [];

        if (empty($enabledEvents)) {
            $enabledEvents = BulkEventEnum::cases();
        }

        return $this->setEvents(
            array_diff($enabledEvents, BulkEventEnum::modelEnd())
        );
    }

    /**
     * Disables the specified events or the all if `$events` equals `null`.
     *
     * @param string[]|null $events
     *
     * @return $this
     */
    public function disableEvents(array $events = null): static
    {
        if ($events === null) {
            return $this->setEvents([]);
        }

        $enabledEvents = $this->getEventDispatcher()->getEnabledEvents() ?? [];

        if (empty($enabledEvents)) {
            $enabledEvents = BulkEventEnum::cases();
        }

        return $this->setEvents(
            array_diff($enabledEvents, $events)
        );
    }

    /**
     * Disables the specified event.
     *
     * @param string $event
     *
     * @return $this
     */
    public function disableEvent(string $event): static
    {
        return $this->disableEvents([$event]);
    }

    /**
     * Enables the specified events or the all if `$events` is empty.
     *
     * @param string[]|null $events
     *
     * @return $this
     */
    public function enableEvents(array $events = null): static
    {
        if (empty($events)) {
            return $this->setEvents(BulkEventEnum::cases());
        }

        $enabledEvents = $this->getEventDispatcher()->getEnabledEvents() ?? [];

        if (empty($enabledEvents)) {
            return $this->setEvents($events);
        }

        return $this->setEvents(
            array_unique(
                array_merge($enabledEvents, $events)
            )
        );
    }

    /**
     * Enables the specified event.
     *
     * @param string $event
     *
     * @return $this
     */
    public function enableEvent(string $event): static
    {
        return $this->enableEvents([$event]);
    }

    /**
     * Sets the list of attribute names which should update.
     *
     * @param string[] $attributes
     *
     * @return $this
     */
    public function updateOnly(array $attributes): static
    {
        $this->updateOnly = $attributes;

        return $this;
    }

    /**
     * Sets the list of attribute names which shouldn't update.
     *
     * @param string[] $attributes
     *
     * @return $this
     */
    public function updateAllExcept(array $attributes): static
    {
        $this->updateExcept = $attributes;

        return $this;
    }

    /**
     * Creates the rows.
     *
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass> $rows
     * @param bool $ignoreConflicts
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function create(iterable $rows, bool $ignoreConflicts = false): static
    {
        $storageKey = $ignoreConflicts ? 'createOrIgnore' : 'create';
        $this->accumulate(
            $storageKey,
            $rows,
            $this->getEventDispatcher()->hasListeners(BulkEventEnum::saved())
            || $this->getEventDispatcher()->hasListeners(BulkEventEnum::created())
            || $this->getEventDispatcher()->hasListeners(BulkEventEnum::deleted())
            || !empty($this->model->getTouchedRelations())
        );

        foreach ($this->getReadyChunks($storageKey, force: true) as $accumulation) {
            $this->runCreateScenario($accumulation, $ignoreConflicts);
        }

        return $this;
    }

    /**
     * Creates the rows if their quantity is greater than or equal to the chunk size.
     *
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass> $rows
     * @param bool $ignoreConflicts
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function createOrAccumulate(iterable $rows, bool $ignoreConflicts = false): static
    {
        $storageKey = $ignoreConflicts ? 'createOrIgnore' : 'create';
        $this->accumulate(
            $storageKey,
            $rows,
            $this->getEventDispatcher()->hasListeners(BulkEventEnum::saved())
            || $this->getEventDispatcher()->hasListeners(BulkEventEnum::created())
            || $this->getEventDispatcher()->hasListeners(BulkEventEnum::deleted())
        );

        foreach ($this->getReadyChunks($storageKey) as $accumulation) {
            $this->runCreateScenario($accumulation, $ignoreConflicts);
        }

        return $this;
    }

    /**
     * Creates the rows and returns them.
     *
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass> $rows
     * @param string[] $columns columns that should be selected from the database
     * @param bool $ignoreConflicts
     *
     * @return Collection<Model>|TCollection<TModel>
     *
     * @throws BulkException
     */
    public function createAndReturn(
        iterable $rows,
        array $columns = ['*'],
        bool $ignoreConflicts = false
    ): Collection {
        $storageKey = $ignoreConflicts ? 'createOrIgnore' : 'create';
        $this->accumulate($storageKey, $rows);
        $result = $this->model->newCollection();

        foreach ($this->getReadyChunks($storageKey, force: true) as $accumulation) {
            $this->getEventDispatcher()->once(
                BulkEventEnum::SAVED_MANY,
                function (Collection $collection) use ($result): void {
                    $result->push(...$collection);
                }
            );

            $this->runCreateScenario($accumulation, $ignoreConflicts, $columns);
        }

        return $result;
    }

    /**
     * Updates the rows.
     *
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function update(iterable $rows): static
    {
        $this->accumulate('update', $rows);

        foreach ($this->getReadyChunks('update', force: true) as $accumulation) {
            $this->runUpdateScenario($accumulation);
        }

        return $this;
    }

    /**
     * Updates the rows if their quantity is greater than or equal to the chunk size.
     *
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function updateOrAccumulate(iterable $rows): static
    {
        $this->accumulate('update', $rows);

        foreach ($this->getReadyChunks('update') as $accumulation) {
            $this->runUpdateScenario($accumulation);
        }

        return $this;
    }

    /**
     * Updates the rows and returns them.
     *
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass|TModel> $rows
     * @param string[] $columns columns that should be selected from the database
     *
     * @return Collection<Model>|TCollection<TModel>
     *
     * @throws BulkException
     */
    public function updateAndReturn(iterable $rows, array $columns = ['*']): Collection
    {
        $this->accumulate('update', $rows);
        $result = $this->model->newCollection();

        foreach ($this->getReadyChunks('update', force: true) as $accumulation) {
            $this->getEventDispatcher()->once(
                BulkEventEnum::SAVED_MANY,
                function (Collection $collection) use ($result): void {
                    $result->push(...$collection);
                }
            );

            $this->runUpdateScenario($accumulation, $columns);
        }

        return $result;
    }

    /**
     * Upserts the rows.
     *
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function upsert(iterable $rows): static
    {
        $this->accumulate('upsert', $rows);

        foreach ($this->getReadyChunks('upsert', force: true) as $accumulation) {
            $this->runUpsertScenario($accumulation);
        }

        return $this;
    }

    /**
     * Upserts the rows if their quantity is greater than or equal to the chunk size.
     *
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function upsertOrAccumulate(iterable $rows): static
    {
        $this->accumulate('upsert', $rows);

        foreach ($this->getReadyChunks('upsert') as $accumulation) {
            $this->runUpsertScenario($accumulation);
        }

        return $this;
    }

    /**
     * Upserts the rows and returns them.
     *
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass|TModel> $rows
     * @param string[] $columns columns that should be selected from the database
     *
     * @return Collection<Model>|TCollection<TModel>
     *
     * @throws BulkException
     */
    public function upsertAndReturn(iterable $rows, array $columns = ['*']): Collection
    {
        $this->accumulate('upsert', $rows);
        $result = $this->model->newCollection();

        foreach ($this->getReadyChunks('upsert', force: true) as $accumulation) {
            $listenerKey = $this->getEventDispatcher()->listen(
                BulkEventEnum::SAVED_MANY,
                function (Collection $collection) use ($result): void {
                    $result->push(...$collection);
                }
            );

            $this->runUpsertScenario($accumulation, $columns);

            $this->getEventDispatcher()->forget(BulkEventEnum::SAVED_MANY, $listenerKey);
        }

        return $result;
    }

    /**
     * Deletes the rows.
     *
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws BulkBindingResolution
     * @throws BulkException
     */
    public function delete(iterable $rows): static
    {
        $this->accumulate('delete', $rows);

        foreach ($this->getReadyChunks('delete', force: true) as $accumulation) {
            $this->runDeleteScenario($accumulation, force: false);
        }

        return $this;
    }

    /**
     * Deletes the rows if their quantity is greater than or equal to the chunk size.
     *
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function deleteOrAccumulate(iterable $rows): static
    {
        $this->accumulate('delete', $rows);

        foreach ($this->getReadyChunks('delete') as $accumulation) {
            $this->runDeleteScenario($accumulation, force: false);
        }

        return $this;
    }

    /**
     * Force deletes the rows.
     *
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws BulkBindingResolution
     * @throws BulkException
     */
    public function forceDelete(iterable $rows): static
    {
        $this->accumulate('forceDelete', $rows);

        foreach ($this->getReadyChunks('forceDelete', force: true) as $accumulation) {
            $this->runDeleteScenario($accumulation, force: true);
        }

        return $this;
    }

    /**
     * Force deletes the rows if their quantity is greater than or equal to the chunk size.
     *
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function forceDeleteOrAccumulate(iterable $rows): static
    {
        $this->accumulate('forceDelete', $rows);

        foreach ($this->getReadyChunks('forceDelete') as $accumulation) {
            $this->runDeleteScenario($accumulation, force: true);
        }

        return $this;
    }

    /**
     * Creates the all accumulated rows.
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function createAccumulated(): static
    {
        foreach ($this->getReadyChunks('createOrIgnore', force: true) as $accumulation) {
            $this->runCreateScenario($accumulation, ignore: true);
        }

        foreach ($this->getReadyChunks('create', force: true) as $accumulation) {
            $this->runCreateScenario($accumulation, ignore: false);
        }

        return $this;
    }

    /**
     * Updates the all accumulated rows.
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function updateAccumulated(): static
    {
        foreach ($this->getReadyChunks('update', force: true) as $accumulation) {
            $this->runUpdateScenario($accumulation);
        }

        return $this;
    }

    /**
     * Upserts the all accumulated rows.
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function upsertAccumulated(): static
    {
        foreach ($this->getReadyChunks('upsert', force: true) as $accumulation) {
            $this->runUpsertScenario($accumulation);
        }

        return $this;
    }

    /**
     * Deletes the all accumulated rows.
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function deleteAccumulated(): static
    {
        foreach ($this->getReadyChunks('delete', force: true) as $accumulation) {
            $this->runDeleteScenario($accumulation, force: false);
        }

        return $this;
    }

    /**
     * Deletes the all accumulated rows.
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function forceDeleteAccumulated(): static
    {
        foreach ($this->getReadyChunks('forceDelete', force: true) as $accumulation) {
            $this->runDeleteScenario($accumulation, force: true);
        }

        return $this;
    }

    /**
     * Saves the all accumulated rows.
     *
     * @return $this
     *
     * @throws BulkException
     */
    public function saveAccumulated(): static
    {
        return $this->createAccumulated()
            ->updateAccumulated()
            ->upsertAccumulated()
            ->deleteAccumulated()
            ->forceDeleteAccumulated();
    }

    /**
     * Adds soft deleted rows to queries.
     *
     * @return $this
     */
    public function withTrashed(): static
    {
        $this->withTrashed = true;

        return $this;
    }

    /**
     * Accumulates the rows to the storage.
     *
     * @param string $storageKey
     * @param iterable<int|string, array<string, mixed>|Model|object|stdClass|TModel> $rows
     * @param bool $uniqueAttributesAreRequired
     *
     * @return void
     *
     * @throws BulkBindingResolution
     */
    private function accumulate(string $storageKey, iterable $rows, bool $uniqueAttributesAreRequired = true): void
    {
        foreach ($rows as $row) {
            $model = $this->convertRowToModel($row);

            if ($uniqueAttributesAreRequired) {
                [$uniqueAttributesIndex, $uniqueAttributes] = $this->getUniqueAttributesForModel($row, $model);

                $this->storage[$storageKey]['i' . $uniqueAttributesIndex] ??= new BulkAccumulationEntity($uniqueAttributes);
                $this->storage[$storageKey]['i' . $uniqueAttributesIndex]->rows[] = new BulkAccumulationItemEntity($row, $model);
            } else {
                $this->storage[$storageKey]['no_unique_attributes'] ??= new BulkAccumulationEntity([]);
                $this->storage[$storageKey]['no_unique_attributes']->rows[] = new BulkAccumulationItemEntity($row, $model);
            }
        }
    }

    /**
     * Converts the specified row to an Eloquent model.
     *
     * @param mixed $row
     *
     * @return Model|TModel
     *
     * @throws BulkBindingResolution
     */
    private function convertRowToModel(mixed $row): Model
    {
        if ($row instanceof Model) {
            return $row;
        }

        if ($row instanceof stdClass) {
            $row = (array) $row;
        }

        if (is_object($row) && method_exists($row, 'toArray')) {
            $row = $row->toArray();
        }

        if (is_array($row) && !empty($row)) {
            try {
                /** @var Model|TModel $result */
                $result = Container::getInstance()->make(
                    get_class($this->model),
                    ['attributes' => $row]
                );
            } catch (BindingResolutionException $exception) {
                throw new BulkBindingResolution(
                    $exception->getMessage(),
                    $exception->getCode(),
                    $exception
                );
            }

            $keyName = $result->getKeyName();

            if (isset($row[$keyName])) {
                $result->{$keyName} = $row[$keyName];
            }

            if ($result->usesTimestamps()) {
                $createdAt = $result->getCreatedAtColumn();
                $updatedAt = $result->getCreatedAtColumn();

                if (array_key_exists($createdAt, $row)) {
                    $result->{$createdAt} = $row[$createdAt];
                }

                if (array_key_exists($updatedAt, $row)) {
                    $result->{$updatedAt} = $row[$updatedAt];
                }
            }

            $deletedAt = $this->getDeletedAtColumn();

            if ($deletedAt !== null && array_key_exists($deletedAt, $row)) {
                $result->{$deletedAt} = $row[$deletedAt];
            }

            return $result;
        }

        throw new BulkValueTypeIsNotSupported($row);
    }

    /**
     * Returns the index and the list of unique attributes that match the specified model.
     *
     * @param mixed $row
     * @param Model|TModel $model
     *
     * @return array<int, int|string[]>
     */
    private function getUniqueAttributesForModel(mixed $row, Model $model): array
    {
        foreach ($this->uniqueBy as $index => $uniqueBy) {
            if ($uniqueBy instanceof Closure) {
                $result = $uniqueBy($row);

                if (is_array($result) || is_string($result)) {
                    return [$index, (array) $result];
                }
            }

            $result = [];

            foreach ($uniqueBy as $attribute) {
                if (!array_key_exists($attribute, $model->getAttributes())) {
                    continue 2;
                }

                $result[] = $attribute;
            }

            return [$index, $result];
        }

        throw new BulkIdentifierDidNotFind($row, $this->uniqueBy);
    }

    /**
     * Returns ready-to-use chunks.
     *
     * @param string $storageKey
     * @param bool $force
     *
     * @return Generator<BulkAccumulationEntity>
     */
    private function getReadyChunks(string $storageKey, bool $force = false): Generator
    {
        foreach ($this->storage[$storageKey] as $key => $accumulation) {
            if ($force) {
                $accumulation->updateOnly = $this->updateOnly;
                $accumulation->updateExcept = $this->updateExcept;
                yield $accumulation;
                unset($this->storage[$storageKey][$key]);
            } elseif (count($accumulation->rows) >= $this->chunkSize) {
                $chunks = array_chunk($accumulation->rows, $this->chunkSize);

                foreach ($chunks as $chunk) {
                    if (count($chunk) === $this->chunkSize) {
                        yield new BulkAccumulationEntity(
                            $accumulation->uniqueBy,
                            $chunk,
                            $this->updateOnly,
                            $this->updateExcept,
                        );
                    } else {
                        $this->storage[$storageKey][$key] = new BulkAccumulationEntity(
                            $accumulation->uniqueBy,
                            $chunk,
                            $this->updateOnly,
                            $this->updateExcept,
                        );
                    }
                }
            }
        }
    }

    /**
     * Runs the creation scenario.
     *
     * @param BulkAccumulationEntity $accumulation
     * @param bool $ignore
     * @param string[] $columns columns that should be selected from the database
     *
     * @return void
     *
     * @throws BulkException
     */
    private function runCreateScenario(BulkAccumulationEntity $accumulation, bool $ignore, array $columns = ['*']): void
    {
        try {
            /** @var CreateScenario $scenario */
            $scenario = Container::getInstance()->make(CreateScenario::class);
            $scenario->handle(
                $this->model,
                $accumulation,
                $this->getEventDispatcher(),
                $ignore,
                $this->getDateFields(),
                $this->getSelectColumns($columns, $accumulation->uniqueBy),
                $this->getDeletedAtColumn(),
            );

            unset($scenario);
        } catch (BindingResolutionException $exception) {
            throw new BulkBindingResolution(
                $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Runs the delete scenario.
     *
     * @param BulkAccumulationEntity $accumulation
     * @param bool $force
     *
     * @return void
     *
     * @throws BulkBindingResolution
     */
    private function runDeleteScenario(BulkAccumulationEntity $accumulation, bool $force): void
    {
        try {
            /** @var DeleteScenario $scenario */
            $scenario = Container::getInstance()->make(DeleteScenario::class);
            $scenario->handle(
                $this->model,
                $accumulation,
                $this->getEventDispatcher(),
                $this->getDateFields(),
                $this->getDeletedAtColumn(),
                $force,
            );

            unset($scenario);
        } catch (BindingResolutionException $exception) {
            throw new BulkBindingResolution(
                $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Runs the update scenario.
     *
     * @param BulkAccumulationEntity $accumulation
     * @param string[] $columns columns that should be selected from the database
     *
     * @return void
     *
     * @throws BulkException
     */
    private function runUpdateScenario(BulkAccumulationEntity $accumulation, array $columns = ['*']): void
    {
        try {
            /** @var UpdateScenario $scenario */
            $scenario = Container::getInstance()->make(UpdateScenario::class);
            $scenario->handle(
                $this->model,
                $accumulation,
                $this->getEventDispatcher(),
                $this->getDateFields(),
                $this->getSelectColumns($columns, $accumulation->uniqueBy),
                $this->getDeletedAtColumn(),
                $this->withTrashed,
            );

            unset($scenario);
        } catch (BindingResolutionException $exception) {
            throw new BulkBindingResolution(
                $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Runs the upsert scenario.
     *
     * @param BulkAccumulationEntity $accumulation
     * @param string[] $columns columns that should be selected from the database
     *
     * @return void
     *
     * @throws BulkException
     */
    private function runUpsertScenario(BulkAccumulationEntity $accumulation, array $columns = ['*']): void
    {
        try {
            /** @var UpsertScenario $scenario */
            $scenario = Container::getInstance()->make(UpsertScenario::class);
            $scenario->handle(
                $this->model,
                $accumulation,
                $this->getEventDispatcher(),
                $this->getDateFields(),
                $this->getSelectColumns($columns, $accumulation->uniqueBy),
                $this->getDeletedAtColumn(),
                $this->withTrashed,
            );

            unset($scenario);
        } catch (BindingResolutionException $exception) {
            throw new BulkBindingResolution(
                $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Returns an array where:
     * - the key is the name of the date(time) field
     * - the value is the date(time) format.
     *
     * @return array<string, string>
     */
    private function getDateFields(): array
    {
        if (!isset($this->dateFields)) {
            $feature = new GetDateFieldsFeature();
            $this->dateFields = $feature->handle($this->model, $this->getDeletedAtColumn());
            unset($feature);
        }

        return $this->dateFields;
    }

    /**
     * Returns the final list of the selecting columns from the database.
     *
     * @param string[] $columns columns that should be selected from the database
     * @param string[] $uniqueBy Unique attributes
     *
     * @return string[]
     */
    private function getSelectColumns(array $columns, array $uniqueBy): array
    {
        if (in_array('*', $columns, true)) {
            return ['*'];
        }

        if ($this->model->getIncrementing()) {
            $columns[] = $this->model->getKeyName();
        } elseif ($this->model->usesTimestamps()) {
            $columns[] = $this->model->getCreatedAtColumn();
        }

        return array_unique(
            array_merge($columns, $uniqueBy)
        );
    }

    /**
     * Returns the name of the `deleted_at` column or `null` if the model
     * doesn't support soft deleting.
     *
     * @return string|null
     */
    private function getDeletedAtColumn(): ?string
    {
        if (!isset($this->deletedAtColumn)) {
            $feature = new GetDeletedAtColumnFeature();
            $this->deletedAtColumn = $feature->handle($this->model);
            unset($feature);
        }

        return $this->deletedAtColumn;
    }

    /**
     * Returns the event dispatcher.
     *
     * @return BulkEventDispatcher
     */
    private function getEventDispatcher(): BulkEventDispatcher
    {
        if (!isset($this->eventDispatcher)) {
            $this->eventDispatcher = new BulkEventDispatcher($this->model);
        }

        return $this->eventDispatcher;
    }
}
