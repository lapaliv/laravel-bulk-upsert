<?php

namespace Lapaliv\BulkUpsert\Enums;

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

}
