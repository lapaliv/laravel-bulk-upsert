<?php

namespace Lapaliv\BulkUpsert\Database\SqlBuilder\Operations;

class BulkSqlBuilderInsert
{
    private string $table;
    private bool $ignore = false;
    private array $fields = [];
    private array $values = [];
    private ?string $primaryKeyName = null;

    public function getTable(): string
    {
        return $this->table;
    }

    public function setTable(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    public function getIgnore(): bool
    {
        return $this->ignore;
    }

    public function setIgnore(bool $value = true): static
    {
        $this->ignore = $value;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param string[] $fields
     * @return $this
     */
    public function setFields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @return mixed[][]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @param mixed[][] $values
     * @return $this
     */
    public function setValues(array $values): static
    {
        $this->values = $values;

        return $this;
    }

    /**
     * @param mixed[] $value
     * @return $this
     */
    public function addValue(array $value): static
    {
        $this->values[] = $value;

        return $this;
    }

    public function getPrimaryKeyName(): ?string
    {
        return $this->primaryKeyName;
    }

    public function setPrimaryKeyName(string $primaryKeyName): static
    {
        $this->primaryKeyName = $primaryKeyName;

        return $this;
    }
}
