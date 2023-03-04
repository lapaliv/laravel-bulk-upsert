<?php

namespace Lapaliv\BulkUpsert\Enums;

class BulkEventEnum
{
    public const CREATING = 'creating';
    public const CREATED = 'created';
    public const UPDATING = 'updating';
    public const UPDATED = 'updated';
    public const SAVING = 'saving';
    public const SAVED = 'saved';
    public const DELETING = 'deleting';
    public const DELETED = 'deleted';
}
