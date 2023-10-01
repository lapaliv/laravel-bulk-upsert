# Laravel Bulk Upsert with eloquent's events

## Annotation

How often do you need to insert a collection of models? I have this task quite often.
Laravel has a solution for mass insert/update/upsert, but it uses a different algorithm than eloquent.
For example, when we are using `$model->create()` we can supplement the data in the observers, and it
doesn't work when we use `Model::query()->insert()`.

The second problem is in the number of fields. We need to align the fields before passing them
to insert/update/upsert method of the builder. This is not always convenient.

The third problem is in the way get the inserted rows back after inserting.
Laravel doesn't return them. Of course, it won't be a big deal if you have only one unique column, but
you will need some time to write quite large SQL query to select them in another case.

Because of the above I have written this library which solves these problems. Using this library you can
save a collection of your models and use eloquent events such as `creating/created`, `updating/updated`, 
`saving/saved`, `deleting/deleted`, `restoring/restored`, `forceDeleting/forceDeleted` at the same time. 
And you don't need to prepare the number of fields before.

In simple terms, this library runs something like this:

```php
foreach($models as $model){
    $model->save();
}
```

but with a few queries to the database per chunk.

## Features
- Creating / Updating / Upserting a collection with firing eloquent events:
  - `creating` / `created`, 
  - `updating` / `updated`, 
  - `saving` / `saved`,
  - `deleting` / `deleted`,
  - `restoring` / `restoried`;
  - `forceDeleting` / `forceDeleted`;
  
  and some new events:
  - `creatingMany` / `createdMany`, 
  - `updatingMany` / `updatedMany`, 
  - `savingMany` / `savedMany`, 
  - `deletingMany` / `deletedMany`,
  - `restoringMany` / `restoredMany`;
  - `forceDeletingMany` / `forceDeletedMany`;
- Automatically align transmitted fields before save them to the database event if you don't use eloquent events.
- Select inserted rows from the database

### Documentation for version 1.x
The documentation for version 1.x you can see [here](https://github.com/lapaliv/laravel-bulk-upsert/blob/feature/v1/README.md)

## Requires

- Database:
  - MySQL: __5.7+__
  - PostgreSQL __9.6+__
- PHP: __8.0+__
- Laravel: __8.0+__

## Installation

You can install the package via composer:

```bash
composer require lapaliv/laravel-bulk-upsert
```

## Get started

Use the trait `Lapaliv\BulkUpsert\Bulkable` in your model(s):

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Lapaliv\BulkUpsert\Bulkable;

class User extends Model {
    use Bulkable;
}
```

## Usage

### Make the instance

There are four ways how you can make an instance

```php
use App\Models\User;

$bulk = User::query()->bulk();
```
```php
use App\Models\User;

$bulk = User::bulk();
```
```php
use App\Models\User;
use Lapaliv\BulkUpsert\Bulk;

$bulk = new Bulk(User::class);
```
```php
use App\Models\User;
use Lapaliv\BulkUpsert\Bulk;

$bulk = new Bulk(new User());
```

### Creating / Inserting

Preparing the data

```php
$data = [
    ['email' => 'john@example.com', 'name' => 'John'],
    ['email' => 'david@example.com', 'name' => 'David'],
];
```

You can just create these users.
```php
$bulk->uniqueBy('email')->create($data);
```

You can create and get back these users.
```php
$users = $bulk->uniqueBy('email')->createAndReturn($data);

// $users is Illuminate\Database\Eloquent\Collection<App\Models\User>
```

You can accumulate rows until there are enough of them to be written.
```php
$chunkSize = 100;
$bulk->uniqueBy('email')
    ->chunk($chunkSize);

foreach($data as $item) {
    // The method `createOrAccumulate` will create rows
    // only when it accumulates the `$chunkSize` rows. 
    $bulk->createOrAccumulate($item);
}

// The createAccumulated method will create all accumulated rows,
// even if their quantity is less than `$chunkSize`.
$bulk->createAccumulated();
```

### Updating

Preparing the data

```php
$data = [
    ['id' => 1, 'email' => 'steve@example.com', 'name' => 'Steve'],
    ['id' => 2, 'email' => 'jack@example.com', 'name' => 'Jack'],
];
```

You can just update these users.
```php
$bulk->update($data);
```

You can update these users and get back a collection of the models.
```php
$users = $bulk->updateAndReturn($data);

// $users is Illuminate\Database\Eloquent\Collection<App\Models\User>
```

You can accumulate rows until there are enough of them to be written.
```php
$chunkSize = 100;
$bulk->chunk($chunkSize);

foreach($data as $item) {
    // The method `updateOrAccumulate` will update rows
    // only when it accumulates the `$chunkSize` rows. 
    $bulk->updateOrAccumulate($item);
}

// The updateAccumulated method will update all accumulated rows,
// even if their quantity is less than `$chunkSize`.
$bulk->updateAccumulated();
```

#### Extra way

There is an extra way how you can update your data in the database:

```php
User::query()
    ->whereIn('id', [1,2,3,4])
    ->selectAndUpdateMany(
        values: ['role' => null],
    );
```

This way loads the data from database and updates found rows by the query.

### Upserting (Updating & Inserting)

Preparing the data

```php
$data = [
    ['email' => 'jacob@example.com', 'name' => 'Jacob'],
    ['id' => 1, 'email' => 'oscar@example.com', 'name' => 'Oscar'],
];
```

You can just upsert these users.
```php
$bulk->uniqueBy(['email'])
    ->upsert($data);
```

You can upsert these users and get back a collection of the models.
```php
$users = $bulk->uniqueBy(['email'])
    ->upsertAndReturn($data);

// $users is Illuminate\Database\Eloquent\Collection<App\Models\User>
```

You also can accumulate rows until there are enough of them to be written.
```php
$chunkSize = 100;
$bulk->uniqueBy(['email'])
    ->chunk($chunkSize);

foreach($data as $item) {
    // The method `upsertOrAccumulate` will upsert rows
    // only when it accumulates the `$chunkSize` rows. 
    $bulk->upsertOrAccumulate($item);
}

// The upsertAccumulated method will upsert all accumulated rows,
// even if their quantity is less than `$chunkSize`.
$bulk->upsertAccumulated();
```

### Force/Soft Deleting (since `v2.1.0`)

Preparing the data

```php
$data = [
    ['email' => 'jacob@example.com', 'name' => 'Jacob'],
    ['id' => 1, 'email' => 'oscar@example.com', 'name' => 'Oscar'],
];
$bulk->create($data);
```

You can just delete these users.
If your model uses the trait `Illuminate\Database\Eloquent\SoftDeletes`, then your model
will delete softly else force.
```php
$bulk->uniqueBy(['email'])
    ->delete($data);
```

Or you can force delete them.
```php
$bulk->uniqueBy(['email'])
    ->forceDelete($data);
```

You also can accumulate rows until there are enough of them to be deleted.
```php
$chunkSize = 100;
$bulk->uniqueBy(['email'])
    ->chunk($chunkSize);

foreach($data as $item) {
    $bulk->deleteOrAccumulate($item);
    // or $bulk->forceDeleteOrAccumulate($item);
}

$bulk->deleteAccumulated();
// or $bulk->forceDeleteAccumulated();
```

### Listeners

#### The order of events
The order of calling callbacks is:
- `onSaving`
- `onCreating` or `onUpdating`
- `onDeleting`
- `onForceDeleting`
- `onRestoring`
- `onSavingMany`
- `onCreatingMany` or `onUpdatingMany`
- `onDeletingMany`
- `onForceDeletingMany`
- `onRestoringMany`
- `onCreated` or `onUpdated`
- `onDeleted`
- `onForceDeleted`
- `onRestored`
- `onCreatedMany` or `onUpdatedMany`
- `onDeletedMany`
- `onForceDeletedMany`
- `onRestoredMany`
- `onSavedMany`

#### How listen to events

There are three ways how you can listen events from the library:

#### How listen to events: Bulk Callbacks

```php
use App\Models\User;
use Lapaliv\BulkUpsert\Collections\BulkRows;

$bulk
    // The callback runs before creating.
    // If your callback returns `false` then the model won't be created
    // and deleted (if `deleted_at` was filled in)
    ->onCreating(fn(User $user) => /* ... */)
    
    // The callback runs after creating.
    ->onCreated(fn(User $user) => /* ... */)
    
    // The callback runs before updating.
    // If your callback returns `false` then the model won't be updated,
    // deleted (if `deleted_at` was filled in) and restored.
    ->onUpdating(fn(User $user) => /* ... */)
    
    // The callback runs after updating.
    ->onUpdated(fn(User $user) => /* ... */)
    
    // The callback runs before deleting.
    // If your callback returns `false` then the model won't be deleted,
    // but it doesn't affect the upserting.
    ->onDeleting(fn(User $user) => /* ... */)
    
    // The callback runs before force deleting.
    // If your callback returns `false` then the model won't be deleted,
    ->onForceDeleting(fn(User $user) => /* ... */)
    
    // The callback runs after deleting.
    ->onDeleted(fn(User $user) => /* ... */)
    
    // The callback runs after force deleting.
    ->onForceDeleted(fn(User $user) => /* ... */)
    
    // The callback runs before upserting.
    ->onSaving(fn(User $user) => /* ... */)
    
    // The callback runs after upserting.
    ->onSaved(fn(User $user) => /* ... */)

    // Runs before creating.
    // If the callback returns `false` then these models won't be created.
    ->onCreatingMany(fn(Collection $users, BulkRows $bulkRows) => /* .. */)

    // Runs after creating.
    ->onCreatedMany(fn(Collection $users, BulkRows $bulkRows) => /* .. */)

    // Runs before updating.
    // If the callback returns `false` then these models won't be updated.
    ->onUpdatingMany(fn(Collection $users, BulkRows $bulkRows) => /* .. */)

    // Runs after updating.
    ->onUpdatedMany(fn(Collection $users, BulkRows $bulkRows) => /* .. */)

    // Runs before deleting.
    // If the callback returns `false` then these models won't be deleted,
    // but it doesn't affect the upserting.
    ->onDeletingMany(fn(Collection $users, BulkRows $bulkRows) => /* .. */)

    // Runs before force deleting.
    // If the callback returns `false` then these models won't be deleted.
    ->onForceDeletingMany(fn(Collection $users, BulkRows $bulkRows) => /* .. */)

    // Runs after deleting.
    ->onDeletedMany(fn(Collection $users, BulkRows $bulkRows) => /* .. */)

    // Runs after force deleting.
    ->onForceDeletedMany(fn(Collection $users, BulkRows $bulkRows) => /* .. */)

    // Runs before restoring.
    // If the callback returns `false` then these models won't be restored,
    // but it doesn't affect the upserting.
    ->onRestoringMany(fn(Collection $users, BulkRows $bulkRows) => /* .. */)

    // Runs after restoring.
    ->onRestoredMany(fn(Collection $users, BulkRows $bulkRows) => /* .. */)

    // Runs before upserting.
    // If the callback returns `false` then these models won't be upserting,
    ->onSavingMany(fn(Collection $users, BulkRows $bulkRows) => /* .. */)

    // Runs after upserting.
    ->onSavedMany(fn(Collection $users, BulkRows $bulkRows) => /* .. */)
```

#### How listen to events: Model Callbacks

You also can use model callbacks. They are almost the same. Just remove the prefix `on`.
For example:

```php
use App\Models\User;
use Lapaliv\BulkUpsert\Collections\BulkRows;

User::saving(fn(User $user) => /* .. */);
User::savingMany(
    fn(Collection $users, BulkRows $bulkRows) => /* .. */
);
```

#### How listen to events: Observer

You also can use observers. For example:

```php
namespace App\Observers;

use App\Models\User;
use Lapaliv\BulkUpsert\Collections\BulkRows;

class UserObserver {
    public function creating(User $user) {
        // ..
    }
    
    public function creatingMany(Collection $users, BulkRows $bulkRows) {
        // ..
    }
}
```

#### Example

You can observe the process. The library supports the base eloquent's events and
some extra which are give you the access to the collection of models.
Listeners with collections accept extra parameter with type `BulkRows`.
This is a collection of class `Lapaliv\BulkUpsert\Entities\BulkRow` which
contains your original data (the property `original`), the model (the property `model`)
and the unique attributes which were used for saving (the property `unique`). It can
help you to continue your saving if, for example, you had some relations in your data.

Let's look at the example:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model {
    // ...
    protected $fillable = ['user_id', 'text', 'uuid'];
    // ...
}

class User extends Model {
    // ...
    protected $fillable = ['email', 'name'];
    // ...
}
```

```php
namespace App\Observers;

use Illuminate\Database\Eloquent\Collection;
use Lapaliv\BulkUpsert\Collections\BulkRows;
use Lapaliv\BulkUpsert\Entities\BulkRow;

class UserObserver {
    public function savedMany(Collection $users, BulkRows $bulkRows): void {
        $rawComments = [];
        
        $bulkRows->each(
            function(BulkRow $bulkRow) use(&$rawComments): void {
                $bulkRow->original['user_id'] = $bulkRow->model->id;
                $rawComments[] = $bulkRow->original;
            }
        )
        
        Comment::query()
            ->bulk()
            ->uniqueBy(['uuid'])
            ->upsert($rawComments);
    }
}
```

```php
$data = [
    [
        'id' => 1,
        'email' => 'tom@example.com',
        'name' => 'Tom',
        'comments' => [
            ['text' => 'First comment', 'uuid' => 'c0753127-45af-43ac-9664-60b5b2dbf0e5'],
            ['text' => 'Second comment', 'uuid' => 'e95d7e15-1e9f-44c5-9978-7641a3792669'],
        ],
    ]
];

User::query()
    ->uniqueBy(['email'])
    ->upsert($data);
```

In this example you have a chain. After upserting the user, the library will run
`UserObserver::savedMany()` where the code will prepare comments and will upsert them.

## API

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class Bulk {

    public function __construct(Model|string $model);
    
    /**
     * Sets the chunk size.
     */
    public function chunk(int $size = 100): static;
    
    /**
     * Defines the unique attributes of the rows.
     * @param callable|string|string[]|string[][] $attributes
     */
    public function uniqueBy(string|array|callable $attributes): static;
    
    /**
     * Defines the alternatives of the unique attributes.
     * @param callable|string|string[]|string[][] $attributes
     */
    public function orUniqueBy(string|array|callable $attributes): static;
    
    /**
     * Sets enabled events.
     * @param string[] $events
     */
    public function setEvents(array $events): static;
    
    /**
     * Disables the next events: `saved`, `created`, `updated`, `deleted`, `restored`.
     */
    public function disableModelEndEvents(): static;
    
    /**
     * Disables the specified events or the all if `$events` equals `null`.
     * @param string[]|null $events
     */
    public function disableEvents(array $events = null): static;
    
    /**
     * Disables the specified event.
     */
    public function disableEvent(string $event): static;
    
    /**
     * Enables the specified events or the all if `$events` is empty.
     * @param string[]|null $events
     */
    public function enableEvents(array $events = null): static;
    
    /**
     * Enables the specified event.
     */
    public function enableEvent(string $event): static;
    
    /**
     * Sets the list of attribute names which should update.
     * @param string[] $attributes
     */
    public function updateOnly(array $attributes): static;
    
    /**
     * Sets the list of attribute names which shouldn't update.
     * @param string[] $attributes
     */
    public function updateAllExcept(array $attributes): static;
    
    /**
     * Enables soft deleted rows into select.
     * 
     * @return $this
     */
    public function withTrashed(): static;
    
    /**
     * Creates the rows.
     * @param iterable<int|string, Model|stdClass|array<string, mixed>|object> $rows
     * @throws BulkException
     */
    public function create(iterable $rows, bool $ignoreConflicts = false): static;
    
    /**
     * Creates the rows if their quantity is greater than or equal to the chunk size.
     * @param iterable<int|string, Model|stdClass|array<string, mixed>|object> $rows
     * @throws BulkException
     */
    public function createOrAccumulate(iterable $rows, bool $ignoreConflicts = false): static;
    
    /**
     * Creates the rows and returns them.
     * @param iterable<int|string, Model|stdClass|array<string, mixed>|object> $rows
     * @param string[] $columns columns that should be selected from the database
     * @return Collection<Model>
     * @throws BulkException
     */
    public function createAndReturn(iterable $rows, array $columns = ['*'], bool $ignoreConflicts = false): Collection;
    
    /**
     * Creates the all accumulated rows.
     * @throws BulkException
     */
    public function createAccumulated(): static;
    
    /**
     * Updates the rows.
     * @param iterable<int|string, Model|stdClass|array<string, mixed>|object> $rows
     * @throws BulkException
     */
    public function update(iterable $rows): static;
    
    /**
     * Updates the rows if their quantity is greater than or equal to the chunk size.
     * @param iterable<int|string, Model|stdClass|array<string, mixed>|object> $rows
     * @throws BulkException
     */
    public function updateOrAccumulate(iterable $rows): static;
    
    /**
     * Updates the rows and returns them.
     * @param iterable<int|string, Model|stdClass|array<string, mixed>|object> $rows
     * @param string[] $columns columns that should be selected from the database
     * @return Collection<Model>
     * @throws BulkException
     */
    public function updateAndReturn(iterable $rows, array $columns = ['*']): Collection;
    
    /**
     * Updates the all accumulated rows.
     * @throws BulkException
     */
    public function updateAccumulated(): static;
    
    /**
     * Upserts the rows.
     * @param iterable<int|string, Model|stdClass|array<string, mixed>|object> $rows
     * @throws BulkException
     */
    public function upsert(iterable $rows): static;
    
    /**
     * Upserts the rows if their quantity is greater than or equal to the chunk size.
     * @param iterable<int|string, Model|stdClass|array<string, mixed>|object> $rows
     * @throws BulkException
     */
    public function upsertOrAccumulate(iterable $rows): static;
    
    /**
     * Upserts the rows and returns them.
     * @param iterable<int|string, Model|stdClass|array<string, mixed>|object> $rows
     * @param string[] $columns columns that should be selected from the database
     * @return Collection<Model>
     * @throws BulkException
     */
    public function upsertAndReturn(iterable $rows, array $columns = ['*']): Collection;
    
    /**
     * Upserts the all accumulated rows.
     * @throws BulkException
     */
    public function upsertAccumulated(): static;
    
    /**
     * Deletes the rows.
     * @param iterable<int|string, Model|stdClass|array<string, mixed>|object> $rows
     * @throws BulkException
     * @since 2.1.0
     */
    public function delete(iterable $rows): static;
    
    /**
     * Deletes the rows if their quantity is greater than or equal to the chunk size.
     * @param iterable<int|string, Model|stdClass|array<string, mixed>|object> $rows
     * @throws BulkException
     * @since 2.1.0
     */
    public function deleteOrAccumulate(iterable $rows): static;
    
    /**
     * Force deletes the rows.
     * @param iterable<int|string, Model|stdClass|array<string, mixed>|object> $rows
     * @throws BulkException
     * @since 2.1.0
     */
    public function forceDelete(iterable $rows): static;
    
    /**
     * Force deletes the rows if their quantity is greater than or equal to the chunk size.
     * @param iterable<int|string, Model|stdClass|array<string, mixed>|object> $rows
     * @throws BulkException
     * @since 2.1.0
     */
    public function forceDeleteOrAccumulate(iterable $rows): static;
    
    /**
     * Deletes the all accumulated rows.
     *
     * @throws BulkException
     * @since 2.1.0
     */
    public function deleteAccumulated(): static;
    
    /**
     * Deletes the all accumulated rows.
     *
     * @throws BulkException
     * @since 2.1.0
     */
    public function forceDeleteAccumulated(): static;
    
    /**
     * Saves the all accumulated rows.
     * @throws BulkException
     */
    public function saveAccumulated(): static;   
}
```

```php
namespace Lapaliv\BulkUpsert\Entities;

class BulkRow {
    /**
     * The upserting/upserted model.
     * @var Model 
     */
    public Model $model;
    
    /**
     * The original item from `iterable rows`. 
     * @var array|object|stdClass|Model 
     */
    public mixed $original;
    
    /**
     * Unique fields which were used for upserting.
     * @var string[] 
     */
    public array $unique;
}
```

### TODO
* Bulk restoring
* Bulk touching
* Bulk updating without updating timestamps
* Supporting `DB::raw()` as a value
* Supporting `SQLite`
* Support a custom database driver

### Tests

You can check the [actions](https://github.com/lapaliv/laravel-bulk-upsert/actions?query=branch%3Amaster) or run it on your laptop: 

```shell
git clone https://github.com/lapaliv/laravel-bulk-upsert.git
cp .env.example .env
docker-composer up -d
./vendor/bin/phpunit
```
