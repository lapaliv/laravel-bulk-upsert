<?php

namespace Lapaliv\BulkUpsert\DatabaseDrivers;

interface BulkDatabaseDriver
{
    /**
     * @param string[] $fields
     * @param bool $ignoring
     * @return int|null
     */
    public function insert(array $fields, bool $ignoring): ?int;

    /**
     * @param string[] $columns
     * @return \stdClass[]
     */
    public function selectAffectedRows(array $columns): array;
}