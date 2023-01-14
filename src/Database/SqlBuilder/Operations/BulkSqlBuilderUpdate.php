<?php

namespace Lapaliv\BulkUpsert\Database\SqlBuilder\Operations;

use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\BulkSqlBuilderCaseClause;
use Lapaliv\BulkUpsert\Database\SqlBuilder\Clauses\BulkSqlBuilderWhereClause;

class BulkSqlBuilderUpdate
{
    private string $table;
    private bool $ignore = false;
    private int $limit;

    /**
     * @var array<string, BulkSqlBuilderCaseClause>
     */
    private array $sets = [];

    public function __construct(private BulkSqlBuilderWhereClause $whereClause)
    {
        //
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function table(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    public function getIgnore(): bool
    {
        return $this->ignore;
    }

    public function ignore(bool $value = true): static
    {
        $this->ignore = $value;

        return $this;
    }

    public function getSet(string $field): ?BulkSqlBuilderCaseClause
    {
        return $this->sets[$field] ?? null;
    }

    public function getSets(): array
    {
        return $this->sets;
    }

    public function set(string $field, BulkSqlBuilderCaseClause $value): static
    {
        $this->sets[$field] = $value;

        return $this;
    }

    public function where(): BulkSqlBuilderWhereClause
    {
        return $this->whereClause;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }
}
