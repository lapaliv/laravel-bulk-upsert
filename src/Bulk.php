<?php

namespace Lapaliv\BulkUpsert;

use BadMethodCallException;
use Closure;
use Generator;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationEntity;
use Lapaliv\BulkUpsert\Entities\BulkAccumulationItemEntity;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Events\BulkEventDispatcher;
use Lapaliv\BulkUpsert\Exceptions\BulkIdentifierDidNotFind;
use Lapaliv\BulkUpsert\Exceptions\BulkModelIsUndefined;
use Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported;
use Lapaliv\BulkUpsert\Scenarios\InsertScenario;
use Lapaliv\BulkUpsert\Scenarios\UpdateScenario;
use stdClass;

/**
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

    private BulkModel $model;
    private int $chunkSize = self::DEFAULT_CHUNK_SIZE;
    /**
     * @var array<callable|string[]>
     */
    private array $uniqueBy = [];
    private BulkEventDispatcher $eventDispatcher;
    private ?string $deletedAtColumn;

    /**
     * @var string[]
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
    ];

    /**
     * @var string[]
     */
    private array $updateOnly = [];

    /**
     * @var string[]
     */
    private array $updateExcept = [];

    public function __construct(BulkModel|string $model)
    {
        if (is_string($model) && class_exists($model)) {
            $this->model = Container::getInstance()->make($model);
        } elseif ($model instanceof BulkModel) {
            $this->model = $model;
        } else {
            throw new BulkModelIsUndefined();
        }

        $this->uniqueBy([$model->getKeyName()]);
    }

//    abstract public function upsert(iterable $rows): static;
//
//    abstract public function upsertOrAccumulate(iterable $rows): static;
//
//    abstract public function upsertAndReturn(iterable $rows): Collection;

    public function __call(string $name, array $arguments)
    {
        if (str_starts_with($name, 'on')) {
            $event = lcfirst(substr($name, 2));

            if (in_array($event, BulkEventEnum::cases())) {
                $this->getEventDispatcher()->dispatch($event, $arguments[0] ?? null);

                return $this;
            }
        }

        throw new BadMethodCallException('Method ' . static::class . '::' . $name . '() is undefined');
    }

    public function chunk(int $size = self::DEFAULT_CHUNK_SIZE): static
    {
        $this->chunkSize = $size;

        return $this;
    }

    /**
     * @param string[]|string[][] $attributes
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

    public function setEvents(array $events): static
    {
        $this->getEventDispatcher()->restrict($events);

        return $this;
    }

    public function disableEvents(): static
    {
        return $this->setEvents([]);
    }

    public function updateOnly(array $attributes): static
    {
        $this->updateOnly = $attributes;

        return $this;
    }

    public function updateAllExcept(array $attributes): static
    {
        $this->updateExcept = $attributes;

        return $this;
    }

    public function create(iterable $rows, bool $ignoreConflicts = false): static
    {
        $storageKey = $ignoreConflicts ? 'createOrIgnore' : 'create';
        $this->accumulate($storageKey, $rows);

        foreach ($this->getReadyChunks($storageKey, force: true) as $accumulation) {
            $this->runInsertScenario($accumulation, $ignoreConflicts);
        }

        return $this;
    }

    public function createOrAccumulate(iterable $rows, bool $ignoreConflicts = false): static
    {
        $storageKey = $ignoreConflicts ? 'createOrIgnore' : 'create';
        $this->accumulate($storageKey, $rows);

        foreach ($this->getReadyChunks($storageKey) as $accumulation) {
            $this->runInsertScenario($accumulation, $ignoreConflicts);
        }

        return $this;
    }

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

            $this->runInsertScenario($accumulation, $ignoreConflicts, $columns);
        }

        return $result;
    }

    public function update(iterable $rows): static
    {
        $this->accumulate('update', $rows);

        foreach ($this->getReadyChunks('update', force: true) as $accumulation) {
            $this->runUpdateScenario($accumulation);
        }

        return $this;
    }

    public function updateOrAccumulate(iterable $rows): static
    {
        $this->accumulate('update', $rows);

        foreach ($this->getReadyChunks('update') as $accumulation) {
            $this->runUpdateScenario($accumulation);
        }

        return $this;
    }

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

    private function accumulate(string $storageKey, iterable $rows): void
    {
        foreach ($rows as $row) {
            $model = $this->convertRowToModel($row);
            $uniqueAttributes = $this->getUniqueAttributesForModel($row, $model);
            $hash = hash('crc32c', implode(',', $uniqueAttributes));

            $this->storage[$storageKey][$hash] ??= new BulkAccumulationEntity($uniqueAttributes);
            $this->storage[$storageKey][$hash]->rows[] = new BulkAccumulationItemEntity($row, $model);
        }
    }

    private function convertRowToModel(mixed $row): BulkModel
    {
        if ($row instanceof BulkModel) {
            return $row;
        }

        if ($row instanceof stdClass) {
            $row = (array) $row;
        }

        if (is_object($row) && method_exists($row, 'toArray')) {
            $row = $row->toArray();
        }

        if (is_object($row)) {
            $row = get_class_vars(get_class($row));
        }

        if (is_array($row)) {
            /** @var BulkModel $result */
            $result = Container::getInstance()->make(
                get_class($this->model),
                ['attributes' => $row]
            );

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

    private function getUniqueAttributesForModel(mixed $row, BulkModel $model): array
    {
        $modelAttributes = $model->attributesToArray();

        foreach ($this->uniqueBy as $uniqueBy) {
            if ($uniqueBy instanceof Closure) {
                $result = $uniqueBy($row);

                if (is_array($result) || is_string($result)) {
                    return (array) $result;
                }
            }

            $result = [];

            foreach ($uniqueBy as $attribute) {
                if (array_key_exists($attribute, $modelAttributes) === false) {
                    continue 2;
                }

                $result[] = $attribute;
            }

            return $result;
        }

        throw new BulkIdentifierDidNotFind($row, $this->uniqueBy);
    }

    /**
     * @param string $storageKey
     * @param bool $force
     *
     * @return Generator<BulkAccumulationEntity>
     */
    private function getReadyChunks(string $storageKey, bool $force = false): Generator
    {
        foreach ($this->storage[$storageKey] as $key => $accumulation) {
            if ($force) {
                yield $accumulation;
                unset($this->storage[$storageKey][$key]);
            } elseif (count($accumulation->rows) >= $this->chunkSize) {
                $chunks = array_chunk($accumulation->rows, $this->chunkSize);

                foreach ($chunks as $chunk) {
                    if (count($chunk) === $this->chunkSize) {
                        yield new BulkAccumulationEntity(
                            $accumulation->uniqueBy,
                            $chunk
                        );
                    } else {
                        $this->storage[$storageKey][$key] = new BulkAccumulationEntity(
                            $accumulation->uniqueBy,
                            $chunk,
                        );
                    }
                }
            }
        }
    }

    private function runInsertScenario(BulkAccumulationEntity $accumulation, bool $ignore, array $columns = ['*']): void
    {
        /** @var InsertScenario $scenario */
        $scenario = Container::getInstance()->make(InsertScenario::class);
        $scenario->handle(
            $this->model,
            $accumulation,
            $this->getEventDispatcher(),
            $ignore,
            $this->getDateFields(),
            $this->getSelectColumnsForInsert($columns),
            $this->getDeletedAtColumn(),
        );
    }

    private function runUpdateScenario(BulkAccumulationEntity $accumulation, array $columns = ['*']): void
    {
        /** @var UpdateScenario $scenario */
        $scenario = Container::getInstance()->make(UpdateScenario::class);
        $scenario->handle(
            $this->model,
            $accumulation,
            $this->getEventDispatcher(),
            $this->getDateFields(),
            $this->getSelectColumnsForInsert($columns),
            $this->getDeletedAtColumn(),
        );
    }

    private function getDateFields(): array
    {
        if (isset($this->dateFields) === false) {
            $this->dateFields = [];

            foreach ($this->model->getDates() as $field) {
                $this->dateFields[$field] = $this->model->getDateFormat();
            }

            if ($this->getDeletedAtColumn() !== null) {
                $this->dateFields[$this->getDeletedAtColumn()] = $this->model->getDateFormat();
            }

            foreach ($this->model->getCasts() as $key => $value) {
                if (is_string($value) && preg_match('/^(date(?:time)?)(?::(.+?))?$/', $value, $matches)) {
                    if ($matches[1] === 'date') {
                        $this->dateFields[$key] = $matches[2] ?? 'Y-m-d';
                    } else {
                        $this->dateFields[$key] = $matches[2] ?? $this->model->getDateFormat();
                    }
                }
            }
        }

        return $this->dateFields;
    }

    private function getSelectColumnsForInsert(array $columns): array
    {
        if (in_array('*', $columns, true)) {
            return ['*'];
        }

        if ($this->model->getIncrementing()) {
            $columns[] = $this->model->getKeyName();
        } elseif ($this->model->usesTimestamps()) {
            $columns[] = $this->model->getCreatedAtColumn();
        }

        return array_unique($columns);
    }

    private function getDeletedAtColumn(): ?string
    {
        if (isset($this->deletedAtColumn) === false) {
            $this->deletedAtColumn = method_exists($this->model, 'getDeletedAtColumn')
                ? $this->model->getDeletedAtColumn()
                : null;
        }

        return $this->deletedAtColumn;
    }

    private function getEventDispatcher(): BulkEventDispatcher
    {
        if (isset($this->eventDispatcher) === false) {
            $this->eventDispatcher = new BulkEventDispatcher($this->model);
        }

        return $this->eventDispatcher;
    }
}
