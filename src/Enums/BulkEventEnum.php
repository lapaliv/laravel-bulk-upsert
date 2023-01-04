<?php

namespace Lapaliv\BulkUpsert\Enums;

class BulkEventEnum
{
    public const CREATING = 'creating';
    public const CREATED = 'created';
    public const SAVING = 'saving';
    public const SAVED = 'saved';
    public const UPDATING = 'updating';
    public const UPDATED = 'updated';
    public const DELETING = 'deleting';
    public const DELETED = 'deleted';
    public const TRASHING = 'trashing';
    public const TRASHED = 'trashed';
    public const RESTORING = 'restoring';
    public const RESTORED = 'restored';

    public const ALL = [
        self::CREATING,
        self::CREATED,
        self::SAVING,
        self::SAVED,
        self::UPDATING,
        self::UPDATED,
        self::DELETING,
        self::DELETED,
        self::TRASHING,
        self::TRASHED,
        self::RESTORING,
        self::RESTORED,
    ];
}