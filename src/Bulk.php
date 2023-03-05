<?php

namespace Lapaliv\BulkUpsert;

use Closure;
use Generator;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Exceptions\BulkIdentifierDidNotFind;
use Lapaliv\BulkUpsert\Features\GetBulkModelFeature;
use Lapaliv\BulkUpsert\Features\SeparateIterableRowsFeature;

class Bulk
{
    private BulkModel $eloquent;

    private int $chunkSize = 100;

    private array $updateAttributes = [
        'anyway' => null,
        'beforeDelete' => [],
        'beforeRestore' => [],
    ];

    private array $identifies = [];

    private array $listeners = [
        'before' => [],
        'beforeCreate' => [],
        'afterCreate' => [],
        'beforeUpdate' => [],
        'afterUpdate' => [],
        'beforeDelete' => [],
        'afterDelete' => [],
        'beforeRestore' => [],
        'afterRestore' => [],
        'beforeSave' => [],
        'afterSave' => [],
        'after' => [],
    ];

    private array $waitingRows = [
        'insertOrIgnore' => [],
        'insert' => [],
        'update' => [],
        'upsert' => [],
    ];

    private array $events = [
        BulkEventEnum::CREATING,
        BulkEventEnum::CREATED,
        BulkEventEnum::UPDATING,
        BulkEventEnum::UPDATED,
        BulkEventEnum::SAVING,
        BulkEventEnum::SAVED,
        BulkEventEnum::DELETING,
        BulkEventEnum::DELETED,
    ];

    public function __construct(
        private GetBulkModelFeature $getBulkModelFeature,
        private SeparateIterableRowsFeature $separateIterableRowsFeature,
        private BulkInsert $bulkInsert,
        private BulkUpdate $bulkUpdate,
        private BulkUpsert $bulkUpsert,
    ) {
        // Nothing
    }

    public function model(string|BulkModel $model): static
    {
        $this->eloquent = $this->getBulkModelFeature->handle($model);

        return $this;
    }

    public function before(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function after(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function beforeCreate(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function afterCreate(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function beforeUpdate(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function afterUpdate(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function beforeDelete(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function afterDelete(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function beforeRestore(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function afterRestore(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function beforeSave(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function afterSave(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function identifyBy(array $uniqueAttributes): static
    {
        $this->identifies = [$uniqueAttributes];

        return $this;
    }

    public function orIdentifyBy(array $uniqueAttributes): static
    {
        $this->identifies[] = $uniqueAttributes;

        return $this;
    }

    public function updateOnly(array $attributes): static
    {
        $this->updateAttributes['anyway'] = $attributes;

        return $this;
    }

    public function updateOnlyBeforeDelete(array $attributes): static
    {
        $this->updateAttributes['beforeDelete'] = $attributes;

        return $this;
    }

    public function updateOnlyBeforeRestore(array $attributes): static
    {
        $this->updateAttributes['beforeRestore'] = $attributes;

        return $this;
    }

    public function chunk(int $size = 100): static
    {
        $this->chunkSize = $size;

        return $this;
    }

    public function insert(iterable $rows, bool $ignore = false): void
    {
        $this->runInsert($this->getBulkInsertInstance(), $rows, $ignore, force: true);
    }

    public function insertOrAccumulate(iterable $rows, bool $ignore = false): void
    {
        $this->runInsert($this->getBulkInsertInstance(), $rows, $ignore);
    }

    public function insertAndReturn(iterable $rows, array $select = ['*'], bool $ignore = false): Collection
    {
        $result = $this->eloquent->newCollection();
        $bulkInsert = $this->getBulkInsertInstance(
            $select,
            fn (Collection $collection) => $result->push(...$collection),
        );

        $this->runInsert($bulkInsert, $rows, $ignore, force: true);

        return $result;
    }

    public function update(iterable $rows): void
    {
        $this->runUpdate($this->getBulkUpdateInstance(), $rows, force: true);
    }

    public function updateOrAccumulate(iterable $rows): void
    {
        $this->runUpdate($this->getBulkUpdateInstance(), $rows);
    }

    public function updateAndReturn(iterable $rows, array $select = ['*']): Collection
    {
        $result = $this->eloquent->newCollection();
        $bulkUpdate = $this->getBulkUpdateInstance(
            $select,
            fn (Collection $collection) => $result->push(...$collection),
        );

        $this->runUpdate($bulkUpdate, $rows, force: true);

        return $result;
    }

    public function upsert(iterable $rows): void
    {
        $this->runUpsert($this->getBulkUpsertInstance(), $rows, force: true);
    }

    public function upsertOrAccumulate(iterable $rows): void
    {
        $this->runUpsert($this->getBulkUpsertInstance(), $rows);
    }

    public function upsertAndReturn(iterable $rows, array $select = ['*']): Collection
    {
        $result = $this->eloquent->newCollection();
        $bulkUpsert = $this->getBulkUpsertInstance(
            $select,
            fn (Collection $collection) => $result->push(...$collection),
        );
        $this->runUpsert($bulkUpsert, $rows, force: true);

        return $result;
    }

    private function when(string $event, ?callable $callback): static
    {
        if ($callback !== null) {
            $this->listeners[$event][] = is_callable($callback)
                ? Closure::fromCallable($callback)
                : $callback;
        }

        return $this;
    }

    private function accumulate(array &$storage, iterable $rows): Generator
    {
        $generator = $this->separateIterableRowsFeature->handle($this->chunkSize, $rows);

        foreach ($generator as $list) {
            foreach ($list as $item) {
                $identifierIndex = $this->getIdentifierIndexForRow($item);
                $storage[$identifierIndex] ??= [];
                $storage[$identifierIndex][] = $item;

                if (count($storage[$identifierIndex]) % $this->chunkSize === 0) {
                    yield [
                        'rows' => $storage[$identifierIndex],
                        'identifiers' => $this->identifies[$identifierIndex],
                    ];

                    $storage[$identifierIndex] = [];
                }
            }
        }
    }

    private function getIdentifierIndexForRow(object|array $row): int
    {
        foreach ($this->identifies as $index => $attributes) {
            foreach ($attributes as $attribute) {
                if ($row instanceof BulkModel && $row->getAttribute($attribute) === null) {
                    continue 2;
                }

                if (is_array($row) && array_key_exists($attribute, $row) === false) {
                    continue 2;
                }

                if (is_object($row) && (isset($row->{$attribute}) === false || $row->{$attribute} === null)) {
                    continue 2;
                }
            }

            return $index;
        }

        throw new BulkIdentifierDidNotFind($row, $this->identifies);
    }

    private function getBulkInsertInstance(?array $columns = ['*'], ?callable $onSaved = null): BulkInsert
    {
        return $this->bulkInsert
            ->onCreating(
                $this->getSingularListener($this->listeners['beforeCreate'])
            )
            ->onCreated(
                $this->getSingularListener($this->listeners['afterCreate'])
            )
            ->onDeleting(
                $this->getSingularListener($this->listeners['beforeDelete'])
            )
            ->onDeleted(
                $this->getSingularListener($this->listeners['afterDelete'])
            )
            ->onSaved(
                $this->getSavedSingularListener($this->listeners['afterSave'], $onSaved)
            )
            ->chunk($this->chunkSize)
            ->select($columns)
            ->setEvents($this->events);
    }

    private function getBulkUpdateInstance(?array $columns = ['*'], ?callable $onSaved = null): BulkUpdate
    {
        return $this->bulkUpdate
            ->onUpdating(
                $this->getSingularListener($this->listeners['beforeCreate'])
            )
            ->onUpdated(
                $this->getSingularListener($this->listeners['afterCreate'])
            )
            ->onDeleting(
                $this->getSingularListener($this->listeners['beforeDelete'])
            )
            ->onDeleted(
                $this->getSingularListener($this->listeners['afterDelete'])
            )
            ->onRestoring(
                $this->getSingularListener($this->listeners['beforeRestore'])
            )
            ->onRestored(
                $this->getSingularListener($this->listeners['afterRestore'])
            )
            ->onSaving(
                $this->getSingularListener($this->listeners['beforeSave'])
            )
            ->onSaved(
                $this->getSavedSingularListener($this->listeners['afterSave'], $onSaved)
            )
            ->chunk($this->chunkSize)
            ->select($columns)
            ->setEvents($this->events);
    }

    private function getBulkUpsertInstance(?array $columns = ['*'], ?callable $onSaved = null): BulkUpsert
    {
        return $this->bulkUpsert
            ->onUpdating(
                $this->getSingularListener($this->listeners['beforeCreate'])
            )
            ->onUpdated(
                $this->getSingularListener($this->listeners['afterCreate'])
            )
            ->onDeleting(
                $this->getSingularListener($this->listeners['beforeDelete'])
            )
            ->onDeleted(
                $this->getSingularListener($this->listeners['afterDelete'])
            )
            ->onRestoring(
                $this->getSingularListener($this->listeners['beforeRestore'])
            )
            ->onRestored(
                $this->getSingularListener($this->listeners['afterRestore'])
            )
            ->onSaving(
                $this->getSingularListener($this->listeners['beforeSave'])
            )
            ->onSaved(
                $this->getSavedSingularListener($this->listeners['afterSave'], $onSaved)
            )
            ->chunk($this->chunkSize)
            ->select($columns)
            ->setEvents($this->events);
    }

    private function getSingularListener(array $callbacks): ?Closure
    {
        if (empty($callbacks)) {
            return null;
        }

        return static function (Collection $collection) use ($callbacks): Collection {
            foreach ($callbacks as $callback) {
                $collection = $callback($collection) ?? $collection;
            }

            return $collection;
        };
    }

    private function getSavedSingularListener(array $callbacks, ?callable $onSaved): ?Closure
    {
        if (empty($callbacks) && $onSaved === null) {
            return null;
        }

        return static function (Collection $collection) use ($callbacks, $onSaved): void {
            foreach ($callbacks as $callback) {
                $callback(clone $collection);
            }

            if ($onSaved !== null) {
                $onSaved(clone $collection);
            }
        };
    }

    private function runInsert(
        BulkInsert $bulkInsert,
        iterable $rows,
        bool $ignore = false,
        bool $force = false,
    ): void {
        $storageKey = $ignore ? 'insertOrIgnore' : 'insert';
        $storage = &$this->waitingRows[$storageKey];

        foreach ($this->accumulate($storage, $rows) as $config) {
            ['rows' => $chunk, 'identifiers' => $identifiers] = $config;
            $bulkInsert->insert($this->eloquent, $identifiers, $chunk, $ignore);
        }

        if ($force) {
            foreach ($storage as $identifierIndex => $chunk) {
                if (empty($chunk) === false) {
                    $bulkInsert->insert(
                        $this->eloquent,
                        $this->identifies[$identifierIndex],
                        $chunk,
                        $ignore,
                    );
                    $storage[$identifierIndex] = [];
                }
            }
        }
    }

    private function runUpdate(BulkUpdate $bulkUpdate, iterable $rows, bool $force = false): void
    {
        $storage = &$this->waitingRows['update'];

        foreach ($this->accumulate($storage, $rows) as $config) {
            ['rows' => $chunk, 'identifiers' => $identifiers] = $config;
            $bulkUpdate->update($this->eloquent, $chunk, $identifiers);
        }

        if ($force) {
            foreach ($storage as $identifierIndex => $chunk) {
                if (empty($chunk) === false) {
                    $bulkUpdate->update(
                        $this->eloquent,
                        $chunk,
                        $this->identifies[$identifierIndex],
                        $this->updateAttributes['anyway'],
                    );
                    $storage[$identifierIndex] = [];
                }
            }
        }
    }

    private function runUpsert(BulkUpsert $bulkUpsert, iterable $rows, bool $force = false): void
    {
        $storage = &$this->waitingRows['upsert'];

        foreach ($this->accumulate($storage, $rows) as $config) {
            ['rows' => $chunk, 'identifiers' => $identifiers] = $config;
            $bulkUpsert->upsert($this->eloquent, $chunk, $identifiers);
        }

        if ($force) {
            foreach ($storage as $identifierIndex => $chunk) {
                if (empty($chunk) === false) {
                    $bulkUpsert->upsert(
                        $this->eloquent,
                        $chunk,
                        $this->identifies[$identifierIndex],
                        $this->updateAttributes['anyway'],
                    );
                    $storage[$identifierIndex] = [];
                }
            }
        }
    }
}
