<?php

namespace Lapaliv\BulkUpsert\Enums;

/**
 * @internal
 */
class BulkEventEnum
{
    public const CREATING = 'creating';
    public const CREATED = 'created';
    public const CREATING_MANY = 'creatingMany';
    public const CREATED_MANY = 'createdMany';
    public const UPDATING = 'updating';
    public const UPDATED = 'updated';
    public const UPDATING_MANY = 'updatingMany';
    public const UPDATED_MANY = 'updatedMany';
    public const SAVING = 'saving';
    public const SAVED = 'saved';
    public const SAVING_MANY = 'savingMany';
    public const SAVED_MANY = 'savedMany';
    public const DELETING = 'deleting';
    public const DELETED = 'deleted';
    public const DELETING_MANY = 'deletingMany';
    public const DELETED_MANY = 'deletedMany';
    public const RESTORING = 'restoring';
    public const RESTORED = 'restored';
    public const RESTORING_MANY = 'restoringMany';
    public const RESTORED_MANY = 'restoredMany';

    public static function cases(): array
    {
        return [
            self::CREATING,
            self::CREATED,
            self::CREATING_MANY,
            self::CREATED_MANY,
            self::UPDATING,
            self::UPDATED,
            self::UPDATING_MANY,
            self::UPDATED_MANY,
            self::SAVING,
            self::SAVED,
            self::SAVING_MANY,
            self::SAVED_MANY,
            self::DELETING,
            self::DELETED,
            self::DELETING_MANY,
            self::DELETED_MANY,
            self::RESTORING,
            self::RESTORED,
            self::RESTORING_MANY,
            self::RESTORED_MANY,
        ];
    }

    public static function halt(): array
    {
        return [
            self::CREATING,
            self::CREATING_MANY,
            self::UPDATING,
            self::UPDATING_MANY,
            self::SAVING,
            self::SAVING_MANY,
            self::DELETING,
            self::DELETING_MANY,
            self::RESTORING,
            self::RESTORING_MANY,
        ];
    }

    public static function model(): array
    {
        return [
            self::CREATING,
            self::CREATED,
            self::UPDATING,
            self::UPDATED,
            self::SAVING,
            self::SAVED,
            self::DELETING,
            self::DELETED,
            self::RESTORING,
            self::RESTORED,
        ];
    }

    public static function collection(): array
    {
        return [
            self::CREATING_MANY,
            self::CREATED_MANY,
            self::UPDATING_MANY,
            self::UPDATED_MANY,
            self::SAVING_MANY,
            self::SAVED_MANY,
            self::DELETING_MANY,
            self::DELETED_MANY,
            self::RESTORING_MANY,
            self::RESTORED_MANY,
        ];
    }

    public static function insert(): array
    {
        return array_merge(self::inserting(), self::inserted());
    }

    public static function inserting(): array
    {
        return [
            self::SAVING,
            self::CREATING,
            self::DELETING,
            self::SAVING_MANY,
            self::CREATING_MANY,
            self::DELETING_MANY,
        ];
    }

    public static function inserted(): array
    {
        return [
            self::CREATED,
            self::DELETED,
            self::SAVED,
            self::CREATED_MANY,
            self::DELETED_MANY,
            self::SAVED_MANY,
        ];
    }
}
