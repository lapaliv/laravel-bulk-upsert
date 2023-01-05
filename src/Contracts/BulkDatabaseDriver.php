<?php

namespace Lapaliv\BulkUpsert\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface BulkDatabaseDriver
{
    /**
     * @param string[] $fields
     * @param bool $ignoring
     * @return int|null
     */
    public function insert(array $fields, bool $ignoring): ?int;

    /**
     * @return \stdClass[]
     */
    public function selectAffectedRows(): array;

    public function setConnectionName(string $name): static;

    public function setBuilder(Builder $builder): static;

    public function setRows(array $rows): static;

    public function setUniqueAttributes(array $uniqueAttributes): static;

    public function setHasIncrementing(bool $value): static;

    public function setPrimaryKeyName(?string $name): static;

    public function setSelectColumns(array $columns): static;
}
