<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\Eloquent\Builder;
use stdClass;

interface BulkDatabaseDriver
{
    /**
     * @param bool $ignoring
     * @return bool
     */
    public function insert(): bool;

    /**
     * @return stdClass[]
     */
    public function selectAffectedRows(): array;

    public function update(): bool;

    public function setBuilder(Builder $builder): static;

    public function setRows(array $rows): static;

    /**
     * @param string[] $uniqueAttributes
     * @return $this
     */
    public function setUniqueAttributes(array $uniqueAttributes): static;

    public function setHasIncrementing(bool $value): static;

    public function setPrimaryKeyName(?string $name): static;

    /**
     * @param string[] $columns
     * @return $this
     */
    public function setSelectColumns(array $columns): static;
}
