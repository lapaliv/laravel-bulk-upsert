<?php

namespace Lapaliv\BulkUpsert\Enums;

/**
 * The events which the package can dispatch.
 *
 * Every case with the `Many` suffix is dispatched once for the whole collection,
 * the other cases are dispatched for each model.
 *
 * @internal
 */
enum BulkEventEnum: string
{
    case Creating = 'creating';
    case Created = 'created';
    case CreatingMany = 'creatingMany';
    case CreatedMany = 'createdMany';
    case Updating = 'updating';
    case Updated = 'updated';
    case UpdatingMany = 'updatingMany';
    case UpdatedMany = 'updatedMany';
    case Saving = 'saving';
    case Saved = 'saved';
    case SavingMany = 'savingMany';
    case SavedMany = 'savedMany';
    case Deleting = 'deleting';
    case Deleted = 'deleted';
    case DeletingMany = 'deletingMany';
    case DeletedMany = 'deletedMany';
    case ForceDeleting = 'forceDeleting';
    case ForceDeleted = 'forceDeleted';
    case ForceDeletingMany = 'forceDeletingMany';
    case ForceDeletedMany = 'forceDeletedMany';
    case Restoring = 'restoring';
    case Restored = 'restored';
    case RestoringMany = 'restoringMany';
    case RestoredMany = 'restoredMany';

    /**
     * Get the events which can stop the process when a listener returns `false`.
     *
     * @return self[]
     */
    public static function halt(): array
    {
        return [
            self::Creating,
            self::CreatingMany,
            self::Updating,
            self::UpdatingMany,
            self::Saving,
            self::SavingMany,
            self::Deleting,
            self::DeletingMany,
            self::ForceDeleting,
            self::ForceDeletingMany,
            self::Restoring,
            self::RestoringMany,
        ];
    }

    /**
     * Get the events which are dispatched for a single model.
     *
     * @return self[]
     */
    public static function model(): array
    {
        return [
            self::Creating,
            self::Created,
            self::Updating,
            self::Updated,
            self::Saving,
            self::Saved,
            self::Deleting,
            self::Deleted,
            self::ForceDeleting,
            self::ForceDeleted,
            self::Restoring,
            self::Restored,
        ];
    }

    /**
     * Get the events of a single model which are dispatched after the query.
     *
     * @return self[]
     */
    public static function modelEnd(): array
    {
        return [
            self::Created,
            self::Updated,
            self::Saved,
            self::Deleted,
            self::ForceDeleted,
            self::Restored,
        ];
    }

    /**
     * Get the events which are dispatched for the whole collection.
     *
     * @return self[]
     */
    public static function collection(): array
    {
        return [
            self::CreatingMany,
            self::CreatedMany,
            self::UpdatingMany,
            self::UpdatedMany,
            self::SavingMany,
            self::SavedMany,
            self::DeletingMany,
            self::DeletedMany,
            self::ForceDeletingMany,
            self::ForceDeletedMany,
            self::RestoringMany,
            self::RestoredMany,
        ];
    }

    /**
     * Get all the events of the creating process.
     *
     * @return self[]
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function create(): array
    {
        return array_merge(self::creating(), self::created());
    }

    /**
     * Get the events which are dispatched before creating.
     *
     * @return self[]
     */
    public static function creating(): array
    {
        return [
            self::Creating,
            self::CreatingMany,
        ];
    }

    /**
     * Get the events which are dispatched after creating.
     *
     * @return self[]
     */
    public static function created(): array
    {
        return [
            self::Created,
            self::CreatedMany,
        ];
    }

    /**
     * Get all the events of the updating process.
     *
     * @return self[]
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function update(): array
    {
        return array_merge(self::updating(), self::updated());
    }

    /**
     * Get the events which are dispatched before updating.
     *
     * @return self[]
     */
    public static function updating(): array
    {
        return [
            self::Updating,
            self::UpdatingMany,
        ];
    }

    /**
     * Get the events which are dispatched after updating.
     *
     * @return self[]
     */
    public static function updated(): array
    {
        return [
            self::Updated,
            self::UpdatedMany,
        ];
    }

    /**
     * Get all the events of the deleting process.
     *
     * @param bool $force
     *
     * @return self[]
     */
    public static function delete(bool $force = false): array
    {
        return array_merge(self::deleting($force), self::deleted($force));
    }

    /**
     * Get the events which are dispatched before deleting.
     *
     * The force events are added to the list when `$force` is `true`.
     *
     * @param bool $force
     *
     * @return self[]
     */
    public static function deleting(bool $force = false): array
    {
        if ($force) {
            return [
                self::Deleting,
                self::DeletingMany,
                self::ForceDeleting,
                self::ForceDeletingMany,
            ];
        }

        return [
            self::Deleting,
            self::DeletingMany,
        ];
    }

    /**
     * Get the events which are dispatched after deleting.
     *
     * The force events are added to the list when `$force` is `true`.
     *
     * @param bool $force
     *
     * @return self[]
     */
    public static function deleted(bool $force = false): array
    {
        if ($force) {
            return [
                self::Deleted,
                self::DeletedMany,
                self::ForceDeleted,
                self::ForceDeletedMany,
            ];
        }

        return [
            self::Deleted,
            self::DeletedMany,
        ];
    }

    /**
     * Get all the events of the restoring process.
     *
     * @return self[]
     */
    public static function restore(): array
    {
        return array_merge(self::restoring(), self::restored());
    }

    /**
     * Get the events which are dispatched before restoring.
     *
     * @return self[]
     */
    public static function restoring(): array
    {
        return [
            self::Restoring,
            self::RestoringMany,
        ];
    }

    /**
     * Get the events which are dispatched after restoring.
     *
     * @return self[]
     */
    public static function restored(): array
    {
        return [
            self::Restored,
            self::RestoredMany,
        ];
    }

    /**
     * Get all the events of the saving process.
     *
     * @return self[]
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function save(): array
    {
        return array_merge(self::saving(), self::saved());
    }

    /**
     * Get the events which are dispatched before saving.
     *
     * @return self[]
     */
    public static function saving(): array
    {
        return [
            self::Saving,
            self::SavingMany,
        ];
    }

    /**
     * Get the events which are dispatched after saving.
     *
     * @return self[]
     */
    public static function saved(): array
    {
        return [
            self::Saved,
            self::SavedMany,
        ];
    }
}
