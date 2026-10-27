<?php

namespace Lapaliv\BulkUpsert;

use Closure;
use Generator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Exceptions\BulkBadMethodCall;
use Lapaliv\BulkUpsert\Exceptions\BulkBindingResolution;
use Lapaliv\BulkUpsert\Exceptions\BulkIdentifierDidNotFind;
use Lapaliv\BulkUpsert\Exceptions\BulkTransmittedClassIsNotAModel;
use Lapaliv\BulkUpsert\Exceptions\BulkModelDoesNotMatch;
use Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported;
use Lapaliv\BulkUpsert\Scenarios\CreateScenario;
use Lapaliv\BulkUpsert\Scenarios\DeleteScenario;
use Lapaliv\BulkUpsert\Scenarios\UpdateScenario;
use Lapaliv\BulkUpsert\Scenarios\UpsertScenario;
use InvalidArgumentException;
use Lapaliv\BulkUpsert\Support\ModelMetadata;
use Lapaliv\BulkUpsert\Support\ReturnedModels;
use LogicException;
use stdClass;

/**
 * Saves many rows of one model with a small number of queries.
 *
 * The instance accumulates the given rows, splits them into chunks and runs the
 * creating, updating, upserting or deleting scenario for every chunk.
 *
 * Each `onXxx()` method registers a listener of the event `Xxx`.
 *
 * @template TCollection of \Illuminate\Database\Eloquent\Collection
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @psalm-type BulkStorageKey = 'createOrIgnore'|'create'|'update'|'upsert'|'delete'|'forceDelete'
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
 * @method $this onForceDeleting(callable|null $callback)
 * @method $this onForceDeleted(callable|null $callback)
 * @method $this onForceDeletingMany(callable|null $callback)
 * @method $this onForceDeletedMany(callable|null $callback)
 * @method $this onRestoring(callable|null $callback)
 * @method $this onRestored(callable|null $callback)
 * @method $this onRestoringMany(callable|null $callback)
 * @method $this onRestoredMany(callable|null $callback)
 */
class Bulk
{
    /**
     * The number of the rows in one chunk when the size is not set.
     *
     * @var int
     */
    private const int DEFAULT_CHUNK_SIZE = 100;

    /**
     * The model whose rows are saved.
     *
     * @var \Illuminate\Database\Eloquent\Model|TModel
     */
    private Model $model;

    /**
     * The number of the rows in one chunk.
     *
     * @var int
     */
    private int $chunkSize = self::DEFAULT_CHUNK_SIZE;

    /**
     * The lists of the unique attributes, or the callbacks which give such a list.
     *
     * @var list<Closure|list<string>>
     */
    private array $uniqueBy = [];

    /**
     * The dispatcher of the package events.
     *
     * @var \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher|null
     */
    private ?BulkEventDispatcher $eventDispatcher = null;

    /**
     * The name of the `deleted_at` column, or `null` when the model has no one.
     *
     * `false` means the value has not been resolved yet; it is distinct from `null`,
     * which is itself a valid resolved value (a model without soft deleting).
     *
     * @var string|null|false
     */
    private string|null|false $deletedAtColumn = false;

    /**
     * The date(time) fields of the model with their formats.
     *
     * @var array<string, string>|null
     */
    private ?array $dateFields = null;

    /**
     * The accumulated rows grouped by the section and by the unique attributes.
     *
     * @var array{
     *  createOrIgnore: array<string, \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity>,
     *  create: array<string, \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity>,
     *  update: array<string, \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity>,
     *  upsert: array<string, \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity>,
     *  delete: array<string, \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity>,
     *  forceDelete: array<string, \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity>,
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
     * The names of the attributes which should be updated.
     *
     * @var string[]
     */
    private array $updateOnly = [];

    /**
     * The names of the attributes which should not be updated.
     *
     * @var string[]
     */
    private array $updateExcept = [];

    /**
     * Whether the soft deleted rows take part in the queries.
     *
     * @var bool
     */
    private bool $withTrashed = false;

    /**
     * The storages which are being flushed right now.
     *
     * @var array<string, true>
     */
    private array $flushing = [];

    /**
     * Create a new bulk instance for the given model.
     *
     * The class name is resolved through the container. The primary key becomes the
     * unique attribute until `uniqueBy()` changes it.
     *
     * @param class-string<TModel>|TModel $model
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
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

        /** @psalm-suppress RedundantConditionGivenDocblockType, DocblockTypeContradiction */
        if ($model instanceof Model) {
            $this->model = $model;
        } else {
            throw new BulkTransmittedClassIsNotAModel(
                is_object($model) ? get_class($model) : $model
            );
        }

        $this->uniqueBy([$this->model->getKeyName()]);
    }

    /**
     * Handle the dynamic calls of the `onXxx()` methods.
     *
     * The part after `on` must be the name of an event, then the first argument
     * becomes a listener of that event.
     *
     * @param string $name
     * @param array $arguments
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkBadMethodCall
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __call(string $name, array $arguments)
    {
        if (str_starts_with($name, 'on')) {
            $eventName = lcfirst(substr($name, 2));
            $event = BulkEventEnum::tryFrom($eventName);

            if ($event) {
                $this->getEventDispatcher()->listen($event, $arguments[0] ?? null);

                return $this;
            }
        }

        throw new BulkBadMethodCall(static::class, $name);
    }

    /**
     * Set the chunk size.
     *
     * The size is never smaller than one row.
     *
     * @param int $size
     *
     * @return $this
     */
    public function chunk(int $size = self::DEFAULT_CHUNK_SIZE): static
    {
        // The size is used as an `array_chunk()` length, which rejects anything below 1.
        $this->chunkSize = max(1, $size);

        return $this;
    }

    /**
     * Define the unique attributes of the rows.
     *
     * A callback gets the row and gives back the names of the attributes which
     * make that row unique.
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

        $this->uniqueBy = $this->normalizeUniqueByArgument($attributes);

        return $this;
    }

    /**
     * Define the alternatives of the unique attributes.
     *
     * The given names are added to the lists which the instance already has, but a
     * callback replaces all of them.
     *
     * @param callable|string|string[]|string[][] $attributes
     *
     * @return $this
     *
     * @psalm-api
     */
    public function orUniqueBy(string|array|callable $attributes): static
    {
        if (is_callable($attributes)) {
            $this->uniqueBy = [Closure::fromCallable($attributes)];

            return $this;
        }

        $this->uniqueBy = array_merge($this->uniqueBy, $this->normalizeUniqueByArgument($attributes));

        return $this;
    }

    /**
     * Set the events which are enabled.
     *
     * Only these events are dispatched, all the others are muted.
     *
     * @param string[]|\Lapaliv\BulkUpsert\Enums\BulkEventEnum[] $events
     *
     * @return $this
     *
     * @throws \ValueError When one of the given names does not belong to an event.
     */
    public function setEvents(array $events): static
    {
        $this->getEventDispatcher()->restrict($events);

        return $this;
    }

    /**
     * Disable the events of a single model which are dispatched after the query.
     *
     * These events are:
     *
     * - `created`,
     * - `updated`,
     * - `saved`,
     * - `deleted`,
     * - `forceDeleted`,
     * - `restored`.
     *
     * @return $this
     *
     * @psalm-api
     */
    public function disableModelEndEvents(): static
    {
        return $this->setEvents(
            array_filter(
                $this->resolveEnabledEvents(),
                fn(BulkEventEnum $event) => !in_array($event, BulkEventEnum::modelEnd(), true)
            )
        );
    }

    /**
     * Disable the given events, or all of them when `$events` is `null`.
     *
     * @param string[]|\Lapaliv\BulkUpsert\Enums\BulkEventEnum[]|null $events
     *
     * @return $this
     */
    public function disableEvents(?array $events = null): static
    {
        if ($events === null) {
            return $this->setEvents([]);
        }

        $events = $this->normalizeEvents($events);

        return $this->setEvents(
            array_filter(
                $this->resolveEnabledEvents(),
                fn(BulkEventEnum $event) => !in_array($event, $events, true)
            )
        );
    }

    /**
     * Disable the given event.
     *
     * @param string|\Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     *
     * @return $this
     *
     * @psalm-api
     */
    public function disableEvent(string|BulkEventEnum $event): static
    {
        if (is_string($event)) {
            $event = BulkEventEnum::from($event);
        }

        return $this->disableEvents([$event]);
    }

    /**
     * Enable the given events, or all of them when `$events` is empty.
     *
     * The method only ever adds, so it does nothing while nothing has been disabled yet.
     *
     * @param \Lapaliv\BulkUpsert\Enums\BulkEventEnum[]|string[]|null $events
     *
     * @return $this
     */
    public function enableEvents(?array $events = null): static
    {
        if ($events === null || $events === []) {
            return $this->setEvents(BulkEventEnum::cases());
        }

        $enabledEvents = $this->getEventDispatcher()->getEnabledEvents();

        if ($enabledEvents === null) {
            return $this;
        }

        $merged = [];

        foreach ($this->normalizeEvents(array_merge($enabledEvents, $events)) as $event) {
            $merged[$event->value] ??= $event;
        }

        return $this->setEvents(array_values($merged));
    }

    /**
     * Turn a mix of event names and event enums into a list of event enums.
     *
     * @param array<array-key, \Lapaliv\BulkUpsert\Enums\BulkEventEnum|string> $events
     *
     * @return \Lapaliv\BulkUpsert\Enums\BulkEventEnum[]
     */
    private function normalizeEvents(array $events): array
    {
        return array_map(
            static fn(BulkEventEnum|string $event): BulkEventEnum => is_string($event) ? BulkEventEnum::from($event) : $event,
            array_values($events)
        );
    }

    /**
     * Enable the given event.
     *
     * @param string|\Lapaliv\BulkUpsert\Enums\BulkEventEnum $event
     *
     * @return $this
     *
     * @psalm-api
     */
    public function enableEvent(string|BulkEventEnum $event): static
    {
        if (is_string($event)) {
            $event = BulkEventEnum::from($event);
        }

        return $this->enableEvents([$event]);
    }

    /**
     * Set the list of the attribute names which should be updated.
     *
     * @param string[] $attributes
     *
     * @return $this
     *
     * @psalm-api
     */
    public function updateOnly(array $attributes): static
    {
        $this->updateOnly = $attributes;

        return $this;
    }

    /**
     * Set the list of the attribute names which should not be updated.
     *
     * @param string[] $attributes
     *
     * @return $this
     *
     * @psalm-api
     */
    public function updateAllExcept(array $attributes): static
    {
        $this->updateExcept = $attributes;

        return $this;
    }

    /**
     * Create the rows.
     *
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass> $rows
     * @param bool $ignoreConflicts
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
     */
    public function create(iterable $rows, bool $ignoreConflicts = false): static
    {
        $storageKey = $ignoreConflicts ? 'createOrIgnore' : 'create';
        $this->assertNotFlushing($storageKey);
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
     * Create the rows if their quantity is greater than or equal to the chunk size.
     *
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass> $rows
     * @param bool $ignoreConflicts
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
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
     * Create the rows and return them.
     *
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass> $rows
     * @param string[] $columns columns that should be selected from the database
     * @param bool $ignoreConflicts
     *
     * @return \Illuminate\Database\Eloquent\Collection
     *
     * @psalm-return TCollection
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
     */
    public function createAndReturn(
        iterable $rows,
        array $columns = ['*'],
        bool $ignoreConflicts = false
    ): Collection
    {
        $storageKey = $ignoreConflicts ? 'createOrIgnore' : 'create';
        $this->assertNotFlushing($storageKey);
        $this->accumulate($storageKey, $rows);
        /** @var TCollection $result */
        $result = $this->model->newCollection();
        // One collector for the whole call: the result does not depend on the events.
        $returned = new ReturnedModels();

        foreach ($this->getReadyChunks($storageKey, force: true) as $accumulation) {

            $this->runCreateScenario($accumulation, $ignoreConflicts, $columns, $returned);
        }

        $result->push(...$returned->all());

        return $result;
    }

    /**
     * Update the rows.
     *
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function update(iterable $rows): static
    {
        $this->assertNotFlushing('update');
        $this->accumulate('update', $rows);

        foreach ($this->getReadyChunks('update', force: true) as $accumulation) {
            $this->runUpdateScenario($accumulation);
        }

        return $this;
    }

    /**
     * Update the rows if their quantity is greater than or equal to the chunk size.
     *
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
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
     * Update the rows and return them.
     *
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass|TModel> $rows
     * @param string[] $columns columns that should be selected from the database
     *
     * @return \Illuminate\Database\Eloquent\Collection
     *
     * @psalm-return TCollection
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
     */
    public function updateAndReturn(iterable $rows, array $columns = ['*']): Collection
    {
        $this->assertNotFlushing('update');
        $this->accumulate('update', $rows);
        /** @var TCollection $result */
        $result = $this->model->newCollection();
        // One collector for the whole call: the result does not depend on the events.
        $returned = new ReturnedModels();

        foreach ($this->getReadyChunks('update', force: true) as $accumulation) {

            $this->runUpdateScenario($accumulation, $columns, $returned);
        }

        $result->push(...$returned->all());

        return $result;
    }

    /**
     * Upsert the rows.
     *
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
     */
    public function upsert(iterable $rows): static
    {
        $this->assertNotFlushing('upsert');
        $this->accumulate('upsert', $rows);

        foreach ($this->getReadyChunks('upsert', force: true) as $accumulation) {
            $this->runUpsertScenario($accumulation);
        }

        return $this;
    }

    /**
     * Upsert the rows if their quantity is greater than or equal to the chunk size.
     *
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
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
     * Upsert the rows and return them.
     *
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass|TModel> $rows
     * @param string[] $columns columns that should be selected from the database
     *
     * @return \Illuminate\Database\Eloquent\Collection
     *
     * @psalm-return TCollection
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
     */
    public function upsertAndReturn(iterable $rows, array $columns = ['*']): Collection
    {
        $this->assertNotFlushing('upsert');
        $this->accumulate('upsert', $rows);
        /** @var TCollection $result */
        $result = $this->model->newCollection();
        // One collector for the whole call: the result does not depend on the events.
        $returned = new ReturnedModels();

        foreach ($this->getReadyChunks('upsert', force: true) as $accumulation) {

            $this->runUpsertScenario($accumulation, $columns, $returned);
        }

        $result->push(...$returned->all());

        return $result;
    }

    /**
     * Delete the rows.
     *
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkBindingResolution
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
     */
    public function delete(iterable $rows): static
    {
        $this->assertNotFlushing('delete');
        $this->accumulate('delete', $rows);

        foreach ($this->getReadyChunks('delete', force: true) as $accumulation) {
            $this->runDeleteScenario($accumulation, force: false);
        }

        return $this;
    }

    /**
     * Delete the rows if their quantity is greater than or equal to the chunk size.
     *
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
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
     * Force delete the rows.
     *
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkBindingResolution
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
     */
    public function forceDelete(iterable $rows): static
    {
        $this->assertNotFlushing('forceDelete');
        $this->accumulate('forceDelete', $rows);

        foreach ($this->getReadyChunks('forceDelete', force: true) as $accumulation) {
            $this->runDeleteScenario($accumulation, force: true);
        }

        return $this;
    }

    /**
     * Force delete the rows if their quantity is greater than or equal to the chunk size.
     *
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass|TModel> $rows
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
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
     * Create all the accumulated rows.
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function createAccumulated(): static
    {
        $this->assertNotFlushing('createOrIgnore', 'create');
        foreach ($this->getReadyChunks('createOrIgnore', force: true) as $accumulation) {
            $this->runCreateScenario($accumulation, ignore: true);
        }

        foreach ($this->getReadyChunks('create', force: true) as $accumulation) {
            $this->runCreateScenario($accumulation, ignore: false);
        }

        return $this;
    }

    /**
     * Update all the accumulated rows.
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function updateAccumulated(): static
    {
        $this->assertNotFlushing('update');
        foreach ($this->getReadyChunks('update', force: true) as $accumulation) {
            $this->runUpdateScenario($accumulation);
        }

        return $this;
    }

    /**
     * Upsert all the accumulated rows.
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function upsertAccumulated(): static
    {
        $this->assertNotFlushing('upsert');
        foreach ($this->getReadyChunks('upsert', force: true) as $accumulation) {
            $this->runUpsertScenario($accumulation);
        }

        return $this;
    }

    /**
     * Delete all the accumulated rows.
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function deleteAccumulated(): static
    {
        $this->assertNotFlushing('delete');
        foreach ($this->getReadyChunks('delete', force: true) as $accumulation) {
            $this->runDeleteScenario($accumulation, force: false);
        }

        return $this;
    }

    /**
     * Force delete all the accumulated rows.
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    public function forceDeleteAccumulated(): static
    {
        $this->assertNotFlushing('forceDelete');
        foreach ($this->getReadyChunks('forceDelete', force: true) as $accumulation) {
            $this->runDeleteScenario($accumulation, force: true);
        }

        return $this;
    }

    /**
     * Save all the accumulated rows.
     *
     * @return $this
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     *
     * @psalm-api
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
     * Add the soft deleted rows to the queries.
     *
     * @return $this
     *
     * @psalm-api
     */
    public function withTrashed(): static
    {
        $this->withTrashed = true;

        return $this;
    }

    /**
     * Accumulate the rows into the storage.
     *
     * The rows are grouped by the unique attributes which match them, so every group
     * can be saved by a single query. The rows are put together when the unique
     * attributes are not required.
     *
     * @param BulkStorageKey $storageKey
     * @param iterable<int|string, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object|\stdClass|TModel> $rows
     * @param bool $uniqueAttributesAreRequired
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkBindingResolution
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkIdentifierDidNotFind
     */
    private function accumulate(string $storageKey, iterable $rows, bool $uniqueAttributesAreRequired = true): void
    {
        // Every row of the call is converted and checked first, and only then added: a row which
        // is rejected must not leave the rows given before it in the storage, where the next
        // flush would write them. The rows accumulated by earlier calls stay as they are.
        $prepared = [];

        foreach ($rows as $row) {
            $model = $this->convertRowToModel($row);
            $this->assertSameTarget($model);
            // The rows are grouped by the attributes which really identify them, so a callback
            // which gives different attributes for different rows makes different groups.
            $uniqueAttributes = $uniqueAttributesAreRequired
                ? $this->getUniqueAttributesForModel($row, $model)
                : null;
            // A `uniqueBy()` callback may have rebound the model meanwhile.
            $this->assertSameTarget($model);
            $prepared[] = [$row, $model, $uniqueAttributes];
        }

        foreach ($prepared as [$row, $model, $uniqueAttributes]) {
            if ($uniqueAttributes !== null) {
                $groupKey = 'unique:' . serialize($uniqueAttributes);

                $this->storage[$storageKey][$groupKey] ??= new BulkAccumulationEntity(uniqueBy: $uniqueAttributes);
                $this->storage[$storageKey][$groupKey]->addRow(new BulkAccumulationItemEntity($row, $model));
            } else {
                $this->storage[$storageKey]['no_unique_attributes'] ??= new BulkAccumulationEntity();
                $this->storage[$storageKey]['no_unique_attributes']->addRow(new BulkAccumulationItemEntity($row, $model));
            }
        }
    }

    /**
     * Convert the given row to an Eloquent model.
     *
     * An array, an object with the `toArray()` method or an instance of `stdClass` is
     * turned into a model through `newInstance()` of the given model, so the connection,
     * the table and the casts of that model are kept. A model is taken as it is.
     *
     * @param mixed $row
     *
     * @return \Illuminate\Database\Eloquent\Model|TModel
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported
     */
    private function convertRowToModel(mixed $row): Model
    {
        if ($row instanceof Model) {
            if ($row instanceof $this->model) {
                return $row;
            }

            throw new BulkValueTypeIsNotSupported($row);
        }

        if ($row instanceof stdClass) {
            $row = (array)$row;
        }

        if (is_object($row) && method_exists($row, 'toArray')) {
            /** @psalm-suppress MixedAssignment A duck-typed toArray() is not backed by an interface. */
            $row = $row->toArray();
        }

        if (is_array($row) && !empty($row)) {
            // `newInstance()` keeps the connection, the table and the casts of the given model,
            // and fills the attributes only after that, so the runtime casts already apply.
            /** @var array<string, mixed> $row */
            $result = $this->model->newInstance($row);

            $keyName = $result->getKeyName();

            if (isset($row[$keyName])) {
                $result->{$keyName} = $row[$keyName];
            }

            if ($result->usesTimestamps()) {
                $createdAt = $result->getCreatedAtColumn();
                $updatedAt = $result->getUpdatedAtColumn();

                if ($createdAt !== null && array_key_exists($createdAt, $row)) {
                    $result->{$createdAt} = $row[$createdAt];
                }

                if ($updatedAt !== null && array_key_exists($updatedAt, $row)) {
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
     * Make sure the given model is bound to the table and the connection of the bulk.
     *
     * Every statement is built from the bulk's model, so a model of another table or
     * connection would be written into the bulk's table. The connections are compared by
     * their names, the default one resolved, without connecting to the database.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkModelDoesNotMatch
     */
    private function assertSameTarget(Model $model): void
    {
        $expectedConnection = $this->resolveConnectionName($this->model);
        $actualConnection = $this->resolveConnectionName($model);

        if ($model->getTable() !== $this->model->getTable() || $actualConnection !== $expectedConnection) {
            throw new BulkModelDoesNotMatch(
                $model,
                $this->model->getTable(),
                $expectedConnection,
                $model->getTable(),
                $actualConnection,
            );
        }
    }

    /**
     * Get the name of the connection of the model, the default one resolved.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return string|null
     */
    private function resolveConnectionName(Model $model): ?string
    {
        return $model->getConnectionName() ?? Model::getConnectionResolver()?->getDefaultConnection();
    }

    /**
     * Get the list of the unique attributes which match the given model.
     *
     * The first list whose attributes the model has is taken. A callback applies when it
     * names at least one attribute (a non-empty string or a list of them); otherwise the
     * next list is tried.
     *
     * @param mixed $row
     * @param \Illuminate\Database\Eloquent\Model|TModel $model
     *
     * @return list<string>
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkIdentifierDidNotFind
     */
    private function getUniqueAttributesForModel(mixed $row, Model $model): array
    {
        foreach ($this->uniqueBy as $uniqueBy) {
            if ($uniqueBy instanceof Closure) {
                /** @psalm-suppress MixedAssignment The closure is user-supplied. */
                $result = $uniqueBy($row);
                $attributes = $this->normalizeUniqueAttributes($result);

                // A callback which names no attribute, or an attribute the row does not have,
                // does not apply to the row: the same rule as for the static lists.
                if ($attributes === null || array_diff_key(array_flip($attributes), $model->getAttributes()) !== []) {
                    continue;
                }

                return $attributes;
            }

            foreach ($uniqueBy as $attribute) {
                if (!array_key_exists($attribute, $model->getAttributes())) {
                    continue 2;
                }
            }

            /** @var list<string> $uniqueBy Normalized by `uniqueBy()`/`orUniqueBy()`. */
            return $uniqueBy;
        }

        throw new BulkIdentifierDidNotFind($row, $this->uniqueBy);
    }

    /**
     * Turn the given unique attributes into a non-empty list of non-empty names.
     *
     * @param mixed $attributes
     *
     * @return list<string>|null Null when the value does not name any attribute.
     */
    private function normalizeUniqueAttributes(mixed $attributes): ?array
    {
        if (is_string($attributes)) {
            $attributes = [$attributes];
        }

        if (!is_array($attributes) || $attributes === []) {
            return null;
        }

        $result = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($attributes as $attribute) {
            if (!is_string($attribute) || $attribute === '') {
                return null;
            }

            $result[] = $attribute;
        }

        return $result;
    }

    /**
     * Turn the argument of `uniqueBy()`/`orUniqueBy()` into lists of the unique attributes.
     *
     * @param string|string[]|string[][] $attributes
     *
     * @return list<list<string>>
     *
     * @throws \InvalidArgumentException When a list does not name any attribute.
     */
    private function normalizeUniqueByArgument(string|array $attributes): array
    {
        $attributes = is_string($attributes) ? [$attributes] : array_values($attributes);
        $lists = isset($attributes[0]) && is_array($attributes[0]) ? $attributes : [$attributes];
        $result = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($lists as $list) {
            $normalized = $this->normalizeUniqueAttributes($list);

            if ($normalized === null) {
                throw new InvalidArgumentException(
                    'The unique attributes must be a non-empty list of non-empty attribute names.'
                );
            }

            $result[] = $normalized;
        }

        // `$lists` is never empty, and an empty list is rejected above.
        return $result;
    }

    /**
     * Get the chunks which are ready to be saved.
     *
     * A full chunk is always yielded; the rest stays in the storage until `$force` is
     * `true`. What stays in the storage after a flush:
     *
     * - the rows of a chunk whose scenario has finished leave the storage;
     * - the chunks which have not run yet stay;
     * - when a scenario throws, the rows of its chunk whose operation has been completed
     *   (inserted, updated, deleted) leave the storage, and the other rows stay, so the next
     *   flush retries them without writing the completed ones twice.
     *
     * "Completed" means that the statement has run, not that an outer transaction has been
     * committed: after a rollback of such a transaction the transaction is retried with a
     * new bulk instance. The listeners run again for a retried row; they are not undone.
     *
     * A forced flush of the storage started while the same storage is being flushed (e.g. by
     * a listener) is rejected. A non-forced one does nothing; its rows are flushed later.
     *
     * @param BulkStorageKey $storageKey
     * @param bool $force
     *
     * @return \Generator<\Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity>
     *
     * @throws \LogicException When a forced flush of a storage being flushed is started.
     */
    private function getReadyChunks(string $storageKey, bool $force = false): Generator
    {
        if (isset($this->flushing[$storageKey])) {
            if ($force) {
                $this->assertNotFlushing($storageKey);
            }

            return;
        }

        // The rows of every group are taken now: the rows which a listener adds meanwhile
        // (to this or to another group) are saved by the next flush.
        $snapshot = [];

        foreach ($this->storage[$storageKey] as $key => $accumulation) {
            $rows = $accumulation->getRows();

            // A non-forced flush has nothing to do with an incomplete group.
            if ($force || count($rows) >= $this->chunkSize) {
                $snapshot[$key] = [$accumulation->getUniqueBy(), $rows];
            }
        }

        if ($snapshot === []) {
            return;
        }

        $this->flushing[$storageKey] = true;

        try {
            foreach ($snapshot as $key => [$uniqueBy, $rows]) {
                /** @var array<int, true> $finished The object ids of the rows which leave the storage. */
                $finished = [];
                /** @var list<\Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity> $current */
                $current = [];

                try {
                    // `chunkSize` bounds the size of a single statement, also when flushing.
                    foreach (array_chunk($rows, $this->chunkSize) as $chunk) {
                        if (!$force && count($chunk) < $this->chunkSize) {
                            // The incomplete tail waits for more rows or for a forced flush.
                            break;
                        }

                        foreach ($chunk as $row) {
                            $row->resetAttempt();
                        }

                        $current = $chunk;

                        yield new BulkAccumulationEntity(
                            $chunk,
                            $uniqueBy,
                            $this->updateOnly,
                            $this->updateExcept,
                        );

                        foreach ($chunk as $row) {
                            $finished[spl_object_id($row)] = true;
                        }

                        $current = [];
                    }
                } finally {
                    // `$current` is not empty when its scenario has thrown: only its rows whose
                    // operation has been completed leave the storage, the others are retried.
                    foreach ($current as $row) {
                        if ($row->isWritten()) {
                            $finished[spl_object_id($row)] = true;
                        }
                    }

                    $this->removeRows($storageKey, $key, $finished);
                }
            }
        } finally {
            unset($this->flushing[$storageKey]);
        }
    }

    /**
     * Reject a forced flush of a storage which is being flushed right now.
     *
     * It is checked before the given rows are added, so a rejected call changes nothing.
     *
     * @param BulkStorageKey ...$storageKeys
     *
     * @return void
     *
     * @throws \LogicException
     */
    private function assertNotFlushing(string ...$storageKeys): void
    {
        foreach ($storageKeys as $storageKey) {
            if (isset($this->flushing[$storageKey])) {
                throw new LogicException(
                    'The accumulated rows cannot be saved while they are being saved (e.g. from a listener).'
                );
            }
        }
    }

    /**
     * Remove the given rows from the accumulation in one pass, and the accumulation when it
     * gets empty.
     *
     * The rows are matched by identity, so the rows added to the accumulation meanwhile stay.
     *
     * @param BulkStorageKey $storageKey
     * @param string $key
     * @param array<int, true> $removed The object ids of the rows to remove.
     *
     * @return void
     */
    private function removeRows(string $storageKey, string $key, array $removed): void
    {
        if ($removed === [] || !isset($this->storage[$storageKey][$key])) {
            return;
        }

        $accumulation = $this->storage[$storageKey][$key];
        $rest = [];

        foreach ($accumulation->getRows() as $row) {
            if (!isset($removed[spl_object_id($row)])) {
                $rest[] = $row;
            }
        }

        if ($rest === []) {
            unset($this->storage[$storageKey][$key]);
        } else {
            $accumulation->setRows($rest);
        }
    }

    /**
     * Run the creating scenario.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $accumulation
     * @param bool $ignore
     * @param string[] $columns columns that should be selected from the database
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    private function runCreateScenario(
        BulkAccumulationEntity $accumulation,
        bool $ignore,
        array $columns = ['*'],
        ?ReturnedModels $returned = null,
    ): void
    {
        try {
            $scenario = Container::getInstance()->make(CreateScenario::class);
            $scenario->handle(
                $accumulation,
                $this->getEventDispatcher(),
                $ignore,
                $this->getDateFields(),
                $this->getSelectColumns($columns, $accumulation->getUniqueBy()),
                $this->getDeletedAtColumn(),
                $returned,
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
     * Run the deleting scenario.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $accumulation
     * @param bool $force
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkBindingResolution
     */
    private function runDeleteScenario(BulkAccumulationEntity $accumulation, bool $force): void
    {
        try {
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
     * Run the updating scenario.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $accumulation
     * @param string[] $columns columns that should be selected from the database
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    private function runUpdateScenario(
        BulkAccumulationEntity $accumulation,
        array $columns = ['*'],
        ?ReturnedModels $returned = null,
    ): void
    {
        try {
            $scenario = Container::getInstance()->make(UpdateScenario::class);
            $scenario->handle(
                $this->model,
                $accumulation,
                $this->getEventDispatcher(),
                $this->getDateFields(),
                $this->getSelectColumns($columns, $accumulation->getUniqueBy()),
                $this->getDeletedAtColumn(),
                $this->withTrashed,
                $returned,
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
     * Run the upserting scenario.
     *
     * @param \Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity $accumulation
     * @param string[] $columns columns that should be selected from the database
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Contracts\BulkException
     */
    private function runUpsertScenario(
        BulkAccumulationEntity $accumulation,
        array $columns = ['*'],
        ?ReturnedModels $returned = null,
    ): void
    {
        try {
            $scenario = Container::getInstance()->make(UpsertScenario::class);
            $scenario->handle(
                $this->model,
                $accumulation,
                $this->getEventDispatcher(),
                $this->getDateFields(),
                $this->getSelectColumns($columns, $accumulation->getUniqueBy()),
                $this->getDeletedAtColumn(),
                $this->withTrashed,
                $returned,
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
     * Get the date(time) fields of the model.
     *
     * The result is an array where:
     *
     * - the key is the name of the date(time) field,
     * - the value is the date(time) format.
     *
     * @return array<string, string>
     */
    private function getDateFields(): array
    {
        if ($this->dateFields === null) {
            $feature = new ModelMetadata();
            $this->dateFields = $feature->dateFields($this->model, $this->getDeletedAtColumn());
            unset($feature);
        }

        return $this->dateFields;
    }

    /**
     * Get the final list of the columns which are selected from the database.
     *
     * @param string[] $columns columns that should be selected from the database
     * @param string[] $uniqueBy the names of the unique attributes
     *
     * @return string[]
     */
    private function getSelectColumns(array $columns, array $uniqueBy): array
    {
        if (in_array('*', $columns, true)) {
            return ['*'];
        }

        $createdAtColumn = $this->model->getCreatedAtColumn();

        // The primary key is always selected: the rows are updated and deleted by it.
        $columns[] = $this->model->getKeyName();

        if (!$this->model->getIncrementing() && $this->model->usesTimestamps() && $createdAtColumn !== null) {
            $columns[] = $createdAtColumn;
        }

        return array_unique(
            array_merge($columns, $uniqueBy)
        );
    }

    /**
     * Get the name of the `deleted_at` column.
     *
     * The method returns `null` when the model does not support soft deleting.
     *
     * @return string|null
     */
    private function getDeletedAtColumn(): ?string
    {
        if ($this->deletedAtColumn === false) {
            $feature = new ModelMetadata();
            $this->deletedAtColumn = $feature->deletedAtColumn($this->model);
            unset($feature);
        }

        return $this->deletedAtColumn;
    }

    /**
     * Get the dispatcher of the package events.
     *
     * @return \Lapaliv\BulkUpsert\EventDispatchers\BulkEventDispatcher
     */
    private function getEventDispatcher(): BulkEventDispatcher
    {
        if ($this->eventDispatcher === null) {
            $this->eventDispatcher = new BulkEventDispatcher($this->model);
        }

        return $this->eventDispatcher;
    }

    /**
     * Get the events which are enabled right now as a concrete list.
     *
     * @return \Lapaliv\BulkUpsert\Enums\BulkEventEnum[]
     */
    private function resolveEnabledEvents(): array
    {
        return $this->getEventDispatcher()->getEnabledEvents() ?? BulkEventEnum::cases();
    }
}
