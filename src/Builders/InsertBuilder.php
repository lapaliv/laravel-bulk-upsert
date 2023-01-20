<?php

namespace Lapaliv\BulkUpsert\Builders;

class InsertBuilder
{
    private ?string $table = null;

    /**
     * @var string[]
     */
    private array $columns = [];

    /**
     * @var array<int|string, scalar[]>
     */
    private array $values = [];
    private bool $onConflictDoNothing = false;
    private ?UpdateBuilder $onConflictUpdateBuilder = null;

    public function getInto(): ?string
    {
        return $this->table;
    }

    public function into(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @return array<int|string, scalar[]>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @param array<int|string, scalar[]> $row
     * @return $this
     */
    public function addValue(array $row): static
    {
        $this->values[] = $row;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param string[] $columns
     * @return $this
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function doNothingAtConflict(): bool
    {
        return $this->onConflictDoNothing;
    }

    public function onConflictDoNothing(bool $value): static
    {
        if ($value) {
            $this->onConflictUpdateBuilder = null;
        }

        $this->onConflictDoNothing = $value;

        return $this;
    }

    public function doUpdateAtConflict(): bool
    {
        return $this->onConflictUpdateBuilder !== null;
    }

    public function getConflictUpdateBuilder(): ?UpdateBuilder
    {
        return $this->onConflictUpdateBuilder;
    }

    public function onConflictUpdate(UpdateBuilder $builder): static
    {
        $this->onConflictDoNothing = false;
        $this->onConflictUpdateBuilder = $builder;

        return $this;
    }

    public function reset(): static
    {
        $this->table = null;
        $this->columns = [];
        $this->values = [];
        $this->onConflictDoNothing = false;
        $this->onConflictUpdateBuilder = null;

        return $this;
    }
}
