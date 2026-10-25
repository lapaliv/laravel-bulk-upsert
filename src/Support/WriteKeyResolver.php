<?php

namespace Lapaliv\BulkUpsert\Support;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Converters\MixedValueToScalarConverter;
use LogicException;

/**
 * Resolves the rows which the UPDATE/DELETE statements are written by.
 *
 * The existing rows are found through the Eloquent query of the model, so the query
 * respects the global scopes. The statements, however, are built by the package
 * itself. Writing them by the unique attributes would reach the rows which the scopes
 * hide (e.g. the same email of another tenant), so they are written by the primary
 * keys of the found rows instead, exactly as `Model::save()` does.
 *
 * Like `Model::save()`, a model which is given with `exists = true` is trusted as it
 * is and is not checked against the scopes again: the caller has already loaded it.
 *
 * @internal
 */
readonly class WriteKeyResolver
{
    /**
     * Create a new resolver instance.
     *
     * @param \Lapaliv\BulkUpsert\Converters\MixedValueToScalarConverter $scalarConverter
     */
    public function __construct(
        private MixedValueToScalarConverter $scalarConverter,
    )
    {
        //
    }

    /**
     * Get the primary key value which identifies the stored row of the model.
     *
     * Like `Model::getKeyForSaveQuery()`, the original value wins over a changed one.
     * A model which does not exist in the database has no stored row, so it gets null.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return int|float|bool|string|null
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported
     */
    public function key(Model $model): int|float|bool|string|null
    {
        if (!$model->exists || $model->getKeyName() === '') {
            return null;
        }

        return $this->scalarConverter->handle(
            $model->getRawOriginal($model->getKeyName()) ?? $model->getKey()
        );
    }

    /**
     * Resolve the columns and the rows which the statement has to filter by.
     *
     * The models which do not exist in the database are never written. Every existing
     * model must have a primary key: writing it by the unique attributes instead would
     * reach the rows which the global scopes hide, so such a model is an error.
     *
     * @param \Illuminate\Database\Eloquent\Model $eloquent
     * @param iterable<array-key, \Illuminate\Database\Eloquent\Model> $models
     *
     * @return array{columns: string[], models: list<\Illuminate\Database\Eloquent\Model>, filters: list<array<string, scalar>>}
     *
     * @throws \LogicException When an existing model has no primary key value.
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkValueTypeIsNotSupported
     */
    public function resolve(Model $eloquent, iterable $models): array
    {
        $keyName = $eloquent->getKeyName();
        $keyed = [];
        $keyRows = [];

        foreach ($models as $model) {
            if (!$model->exists) {
                continue;
            }

            $key = $this->key($model);

            if ($key === null) {
                throw new LogicException(
                    sprintf('The existing row of %s has no value of the primary key "%s".', $model::class, $keyName)
                );
            }

            $keyed[] = $model;
            $keyRows[] = [$keyName => $key];
        }

        return ['columns' => [$keyName], 'models' => $keyed, 'filters' => $keyRows];
    }
}
