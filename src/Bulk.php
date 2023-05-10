<?php

namespace Lapaliv\BulkUpsert;

use Closure;
use Generator;
use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Lapaliv\BulkUpsert\Enums\BulkEventEnum;
use Lapaliv\BulkUpsert\Exceptions\BulkIdentifierDidNotFind;
use Lapaliv\BulkUpsert\Exceptions\BulkModelWasNotTransferred;
use Lapaliv\BulkUpsert\Features\GetBulkModelFeature;
use Lapaliv\BulkUpsert\Features\SeparateIterableRowsFeature;
use Lapaliv\BulkUpsert\Traits\BulkPreparingBulkUpdateTrait;

class Bulk
{
    use BulkPreparingBulkUpdateTrait;

    private BulkModel $eloquent;

    private int $chunkSize = 100;

    private array $updateAttributes = [
        'onlyAnyway' => [],
        'onlyBeforeDeleting' => [],
        'onlyBeforeRestoring' => [],
    ];

    private array $identifies = [];

    private array $listeners = [
//        'before' => [],
        'beforeCreating' => [],
        'afterCreating' => [],
        'beforeUpdating' => [],
        'afterUpdating' => [],
        'beforeDeleting' => [],
        'afterDeleting' => [],
        'beforeRestoring' => [],
        'afterRestoring' => [],
        'beforeSaving' => [],
        'afterSaving' => [],
//        'after' => [],
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

    public function beforeCreating(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function afterCreating(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function beforeUpdating(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function afterUpdating(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function beforeDeleting(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function afterDeleting(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function beforeRestoring(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function afterRestoring(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function beforeSaving(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function afterSaving(?callable $callback): static
    {
        return $this->when(__FUNCTION__, $callback);
    }

    public function registerObserver(object $observer): static
    {
        foreach (array_keys($this->listeners) as $eventName) {
            if (method_exists($observer, $eventName)) {
                $this->{$eventName}([$observer, $eventName]);
            }
        }

        return $this;
    }

    /**
     * @param string[]|string[][] $uniqueAttributes
     * @return $this
     */
    public function identifyBy(array $uniqueAttributes): static
    {
        $isFlatArray = true;

        foreach ($uniqueAttributes as $item) {
            if (is_array($item)) {
                $this->identifies[] = $item;
                $isFlatArray = false;
            }
        }

        if ($isFlatArray) {
            $this->identifies[] = $uniqueAttributes;
        }

        return $this;
    }

    /**
     * @param string[]|string[][] $uniqueAttributes
     * @return $this
     */
    public function orIdentifyBy(array $uniqueAttributes): static
    {
        $isFlatArray = true;

        foreach ($uniqueAttributes as $item) {
            if (is_array($item)) {
                $this->identifies[] = $item;
                $isFlatArray = false;
            }
        }

        if ($isFlatArray) {
            $this->identifies[] = $uniqueAttributes;
        }

        return $this;
    }

    public function updateOnly(array $attributes): static
    {
        $this->updateAttributes['onlyAnyway'] = $attributes;

        return $this;
    }

    public function updateOnlyBeforeDeleting(array $attributes): static
    {
        $this->updateAttributes['onlyBeforeDeleting'] = array_unique($attributes);

        return $this;
    }

    public function updateOnlyBeforeRestoring(array $attributes): static
    {
        $this->updateAttributes['onlyBeforeRestoring'] = array_unique($attributes);

        return $this;
    }

    public function chunk(int $size = 100): static
    {
        $this->chunkSize = $size;

        return $this;
    }

    public function insert(iterable $rows, bool $ignore = false): static
    {
        $this->insertWithAccumulation($this->getBulkInsertInstance(), $rows, $ignore, force: true);

        return $this;
    }

    public function insertOrAccumulate(iterable $rows, bool $ignore = false): static
    {
        $this->insertWithAccumulation($this->getBulkInsertInstance(), $rows, $ignore);

        return $this;
    }

    public function insertAndReturn(iterable $rows, array $select = ['*'], bool $ignore = false): Collection
    {
        $result = $this->getEloquent()->newCollection();
        $bulkInsert = $this->getBulkInsertInstance(
            $select,
            fn (Collection $collection) => $result->push(...$collection),
        );

        $this->insertWithAccumulation($bulkInsert, $rows, $ignore, force: true);

        return $result;
    }

    public function update(iterable $rows): static
    {
        $this->updateWithAccumulation($this->getBulkUpdateInstance(), $rows, force: true);

        return $this;
    }

    public function updateOrAccumulate(iterable $rows): static
    {
        $this->updateWithAccumulation($this->getBulkUpdateInstance(), $rows);

        return $this;
    }

    public function updateAndReturn(iterable $rows, array $select = ['*']): Collection
    {
        $result = $this->getEloquent()->newCollection();
        $bulkUpdate = $this->getBulkUpdateInstance(
            $select,
            fn (Collection $collection) => $result->push(...$collection),
        );

        $this->updateWithAccumulation($bulkUpdate, $rows, force: true);

        return $result;
    }

    public function upsert(iterable $rows): static
    {
        $this->upsertWithAccumulation($this->getBulkUpsertInstance(), $rows, force: true);

        return $this;
    }

    public function upsertOrAccumulate(iterable $rows): static
    {
        $this->upsertWithAccumulation($this->getBulkUpsertInstance(), $rows);

        return $this;
    }

    public function upsertAndReturn(iterable $rows, array $select = ['*']): Collection
    {
        $result = $this->getEloquent()->newCollection();
        $bulkUpsert = $this->getBulkUpsertInstance(
            $select,
            fn (Collection $collection) => $result->push(...$collection),
        );
        $this->upsertWithAccumulation($bulkUpsert, $rows, force: true);

        return $result;
    }

    public function saveAccumulated(): void
    {
        $bulkInsert = $this->getBulkInsertInstance();
        $bulkUpdate = $this->getBulkUpdateInstance();
        $bulkUpsert = $this->getBulkUpsertInstance();

        if (empty($this->waitingRows['insertOrIgnore']) === false) {
            $this->insertWithoutAccumulation($bulkInsert, $this->waitingRows['insertOrIgnore'], true);
            $this->waitingRows['insertOrIgnore'] = [];
        }

        if (empty($this->waitingRows['insert']) === false) {
            $this->insertWithoutAccumulation($bulkInsert, $this->waitingRows['insert'], true);
            $this->waitingRows['insert'] = [];
        }

        if (empty($this->waitingRows['update']) === false) {
            $this->updateWithoutAccumulation($bulkUpdate, $this->waitingRows['update'], true);
            $this->waitingRows['update'] = [];
        }

        if (empty($this->waitingRows['upsert']) === false) {
            $this->upsertWithoutAccumulation($bulkUpsert, $this->waitingRows['upsert']);
            $this->waitingRows['upsert'] = [];
        }
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
        if (is_object($row) && method_exists($row, 'toArray')) {
            $row = $row->toArray();
        }

        foreach ($this->identifies as $index => $attributes) {
            foreach ($attributes as $attribute) {
                if ($row instanceof BulkModel && $row->getAttribute($attribute) === null) {
                    continue 2;
                }

                if (is_array($row)
                    && (array_key_exists($attribute, $row) === false || empty($row[$attribute]))
                ) {
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
                $this->getSingularListener($this->listeners['beforeCreating'])
            )
            ->onCreated(
                $this->getSingularListener($this->listeners['afterCreating'])
            )
            ->onDeleting(
                $this->getSingularListener($this->listeners['beforeDeleting'])
            )
            ->onDeleted(
                $this->getSingularListener($this->listeners['afterDeleting'])
            )
            ->onSaved(
                $this->getSavedSingularListener($this->listeners['afterSaving'], $onSaved)
            )
            ->chunk($this->chunkSize)
            ->select($columns)
            ->setEvents($this->events);
    }

    private function getBulkUpsertInstance(?array $columns = ['*'], ?callable $onSaved = null): BulkUpsert
    {
        return $this->bulkUpsert
            ->onCreating(
                $this->getSingularListener($this->listeners['beforeCreating'])
            )
            ->onCreated(
                $this->getSingularListener($this->listeners['afterCreating'])
            )
            ->onUpdating($this->getOnUpdatingCallback())
            ->onUpdated(
                $this->getSingularListener($this->listeners['afterUpdating'])
            )
            ->onDeleting(
                $this->getSingularListener($this->listeners['beforeDeleting'])
            )
            ->onDeleted(
                $this->getSingularListener($this->listeners['afterDeleting'])
            )
            ->onRestoring(
                $this->getSingularListener($this->listeners['beforeRestoring'])
            )
            ->onRestored(
                $this->getSingularListener($this->listeners['afterRestoring'])
            )
            ->onSaving(
                $this->getSingularListener($this->listeners['beforeSaving'])
            )
            ->onSaved(
                $this->getSavedSingularListener($this->listeners['afterSaving'], $onSaved)
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

    private function insertWithAccumulation(
        BulkInsert $bulkInsert,
        iterable $rows,
        bool $ignore = false,
        bool $force = false,
    ): void {
        $storageKey = $ignore ? 'insertOrIgnore' : 'insert';
        $storage = &$this->waitingRows[$storageKey];

        foreach ($this->accumulate($storage, $rows) as $config) {
            ['rows' => $chunk, 'identifiers' => $identifiers] = $config;
            $bulkInsert->insert($this->getEloquent(), $identifiers, $chunk, $ignore);
        }

        if ($force) {
            $this->insertWithoutAccumulation($bulkInsert, $storage, $ignore);
            $storage = [];
        }
    }

    private function insertWithoutAccumulation(
        BulkInsert $bulkInsert,
        array $storage,
        bool $ignore = false,
    ): void {
        foreach ($storage as $identifierIndex => $chunk) {
            if (empty($chunk) === false) {
                $bulkInsert->insert(
                    $this->getEloquent(),
                    $this->identifies[$identifierIndex],
                    $chunk,
                    $ignore,
                );
            }
        }
    }

    private function updateWithAccumulation(BulkUpdate $bulkUpdate, iterable $rows, bool $force = false): void
    {
        $storage = &$this->waitingRows['update'];

        foreach ($this->accumulate($storage, $rows) as $config) {
            ['rows' => $chunk, 'identifiers' => $identifiers] = $config;
            $bulkUpdate->update($this->getEloquent(), $chunk, $identifiers);
        }

        if ($force) {
            $this->updateWithoutAccumulation($bulkUpdate, $storage);
            $storage = [];
        }
    }

    private function updateWithoutAccumulation(BulkUpdate $bulkUpdate, array $storage): void
    {
        foreach ($storage as $identifierIndex => $chunk) {
            if (empty($chunk) === false) {
                $bulkUpdate->update(
                    $this->getEloquent(),
                    $chunk,
                    $this->identifies[$identifierIndex],
                );
                $storage[$identifierIndex] = [];
            }
        }
    }

    private function upsertWithAccumulation(BulkUpsert $bulkUpsert, iterable $rows, bool $force = false): void
    {
        $storage = &$this->waitingRows['upsert'];

        foreach ($this->accumulate($storage, $rows) as $config) {
            ['rows' => $chunk, 'identifiers' => $identifiers] = $config;
            $bulkUpsert->upsert($this->getEloquent(), $chunk, $identifiers);
        }

        if ($force) {
            $this->upsertWithoutAccumulation($bulkUpsert, $storage);
            $storage = [];
        }
    }

    private function upsertWithoutAccumulation(BulkUpsert $bulkUpsert, array $storage): void
    {
        foreach ($storage as $identifierIndex => $chunk) {
            if (empty($chunk) === false) {
                $bulkUpsert->upsert(
                    $this->getEloquent(),
                    $chunk,
                    $this->identifies[$identifierIndex],
                );
                $storage[$identifierIndex] = [];
            }
        }
    }

    private function getEloquent(): BulkModel
    {
        if (isset($this->eloquent)) {
            return $this->eloquent;
        }

        throw new BulkModelWasNotTransferred();
    }
}
