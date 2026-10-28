<?php

namespace Lapaliv\BulkUpsert\Support;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Exceptions\BulkModelDoesNotMatch;

/**
 * The table and the connection which a bulk writes into, taken once.
 *
 * It is a snapshot, not a reference to a model: a model is mutable, and a listener may change
 * the table or the connection of a row (or of the bulk's own model) after the rows have been
 * checked. The write steps compare the models with it right before the statement is built.
 *
 * @internal
 */
final readonly class WriteTarget
{
    /**
     * Create a new target.
     *
     * @param string $table
     * @param string|null $connection The name of the connection, the default one resolved.
     */
    public function __construct(
        public string $table,
        public ?string $connection,
    )
    {
        //
    }

    /**
     * Take the target of the given model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return self
     */
    public static function of(Model $model): self
    {
        return new self($model->getTable(), self::connectionName($model));
    }

    /**
     * Make sure every given model is bound to this target.
     *
     * @param iterable<\Illuminate\Database\Eloquent\Model> $models
     *
     * @return void
     *
     * @throws \Lapaliv\BulkUpsert\Exceptions\BulkModelDoesNotMatch
     */
    public function assert(iterable $models): void
    {
        foreach ($models as $model) {
            $connection = self::connectionName($model);

            if ($model->getTable() !== $this->table || $connection !== $this->connection) {
                throw new BulkModelDoesNotMatch(
                    $model,
                    $this->table,
                    $this->connection,
                    $model->getTable(),
                    $connection,
                );
            }
        }
    }

    /**
     * Get the name of the connection of the model, the default one resolved.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return string|null
     */
    private static function connectionName(Model $model): ?string
    {
        return $model->getConnectionName() ?? Model::getConnectionResolver()?->getDefaultConnection();
    }
}
