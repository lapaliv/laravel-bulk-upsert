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

    /**
     * @return string[]
     */
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

    /**
     * @return string[]
     */
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

    /**
     * @return string[]
     */
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

    public static function modelEnd(): array
    {
        return [
            self::CREATED,
            self::UPDATED,
            self::SAVED,
            self::DELETED,
            self::RESTORED,
        ];
    }

    /**
     * @return string[]
     */
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

    /**
     * @return string[]
     */
    public static function create(): array
    {
        return array_merge(self::creating(), self::created());
    }

    /**
     * @return string[]
     */
    public static function creating(): array
    {
        return [
            self::CREATING,
            self::CREATING_MANY,
        ];
    }

    /**
     * @return string[]
     */
    public static function created(): array
    {
        return [
            self::CREATED,
            self::CREATED_MANY,
        ];
    }

    /**
     * @return string[]
     */
    public static function update(): array
    {
        return array_merge(self::updating(), self::updated());
    }

    /**
     * @return string[]
     */
    public static function updating(): array
    {
        return [
            self::UPDATING,
            self::UPDATING_MANY,
        ];
    }

    /**
     * @return string[]
     */
    public static function updated(): array
    {
        return [
            self::UPDATED,
            self::UPDATED_MANY,
        ];
    }

    /**
     * @return string[]
     */
    public static function delete(): array
    {
        return array_merge(self::deleting(), self::deleted());
    }

    /**
     * @return string[]
     */
    public static function deleting(): array
    {
        return [
            self::DELETING,
            self::DELETING_MANY,
        ];
    }

    /**
     * @return string[]
     */
    public static function deleted(): array
    {
        return [
            self::DELETED,
            self::DELETED_MANY,
        ];
    }

    /**
     * @return string[]
     */
    public static function restore(): array
    {
        return array_merge(self::restoring(), self::restored());
    }

    /**
     * @return string[]
     */
    public static function restoring(): array
    {
        return [
            self::RESTORING,
            self::RESTORING_MANY,
        ];
    }

    /**
     * @return string[]
     */
    public static function restored(): array
    {
        return [
            self::RESTORED,
            self::RESTORED_MANY,
        ];
    }

    /**
     * @return string[]
     */
    public static function save(): array
    {
        return array_merge(self::saving(), self::saved());
    }

    /**
     * @return string[]
     */
    public static function saving(): array
    {
        return [
            self::SAVING,
            self::SAVING_MANY,
        ];
    }

    /**
     * @return string[]
     */
    public static function saved(): array
    {
        return [
            self::SAVED,
            self::SAVED_MANY,
        ];
    }
}
