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
  - `restoring` / `restored`;
  - `forceDeleting` / `forceDeleted`;
  
  and some new events:
  - `creatingMany` / `createdMany`, 
  - `updatingMany` / `updatedMany`, 
  - `savingMany` / `savedMany`, 
  - `deletingMany` / `deletedMany`,
  - `restoringMany` / `restoredMany`;
  - `forceDeletingMany` / `forceDeletedMany`;
- Automatically aligning the transmitted fields before saving them to the database, even if you don't use eloquent events.
- Selecting the inserted rows back from the database

### Documentation for version 1.x
The documentation for version 1.x you can see [here](https://github.com/lapaliv/laravel-bulk-upsert/blob/feature/v1/README.md)

## Requires

- Database:
  - MySQL: __5.7+__
  - PostgreSQL __9.6+__
  - SQLite __3.32+__
- PHP: __8.4+__
- Laravel: __11.0+__

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

A new model which you pass to `create()` / `upsert()` is not updated in place: it gets neither
the primary key nor `exists`. The created rows are read back into other model instances, which
`createAndReturn()` / `upsertAndReturn()` give back and the listeners receive. Use them when
you need the keys.

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

- Every found row is loaded as a model and the values are set through `setAttribute()`,
  so the casts (e.g. `hashed`) and the mutators run exactly as for `Model::save()`,
  with or without listeners. There is no shortcut to a single `UPDATE` query.
- The rows are processed chunk by chunk (`chunk`, 100 by default). Without an own
  order, limit or offset they are paginated by the primary key, so a row which the
  update moves out of the `WHERE` clause does not make the next chunks skip rows.
  With an own order, limit or offset (or with `DISTINCT` or `JOIN`) the primary keys of all the
  found rows are collected first, so all of them are kept in memory; the rows are then
  loaded again by these keys.
- The rows are not a frozen snapshot: listeners and other processes may change the
  rows of the next chunks.
- The method returns the number of the selected rows, not the number of the rows
  which have really changed.
- The given builder is not changed.
- A query with a `JOIN` is handled the same way, and a row which the join repeats is
  updated once.
- `GROUP BY`, `HAVING`, `UNION` and a `DISTINCT` query whose columns do not contain
  the primary key are not supported: the method throws a `LogicException` before
  writing anything. The key has to be selected as a plain column (`id`, `users.id`,
  `users.*`, `users.id as id`); a key inside `selectRaw()` is not recognized, so such a
  `DISTINCT` query is rejected too.

### Upserting (Updating & Inserting)

An upsert inserts the rows which it has not found and then updates the others. A row which
appears in the database meanwhile (a duplicate key) is skipped by the insert and updated,
but the other errors which the database raises for a new row (NOT NULL, CHECK, a foreign
key, a too long value) reach the caller instead of being hidden:

- PostgreSQL uses `ON CONFLICT DO NOTHING ... RETURNING`, which skips a uniqueness conflict
  only and returns exactly the created rows.
- SQLite inserts the rows by a plain `INSERT` inside a transaction (a savepoint within yours);
  only when it fails with a duplicate key, they are inserted one by one with
  `ON CONFLICT DO NOTHING`, each one telling whether it was created.
- MySQL has no such clause (`INSERT IGNORE` also hides the other errors), so the rows are
  inserted by a plain `INSERT`; only when it fails with a duplicate key, they are inserted
  again one by one inside a transaction, skipping the duplicates. This relies on
  transactional tables (InnoDB), including the tables which triggers change; the triggers
  of the rows which the first statement had processed before it stopped may run again,
  and the auto-increment counter may skip values. A duplicate key raised by a trigger in
  another table is not told apart from a duplicate of the row: such a row is reported as
  described below. MySQL without the strict mode may still
  convert invalid values by itself: the library no longer hides errors, but it does not
  add a validation of its own.

A new row which the insert has sent, but which is then neither read back nor found by
`uniqueBy()` (e.g. a duplicate key of another unique index skipped it), is reported with
`Lapaliv\BulkUpsert\Exceptions\BulkRowsWereNotSaved` after the other rows of the chunk
have been saved; `getRows()` gives its models. Such a row stays in the accumulated storage. Its result is not
known for sure (a trigger or a collation may have stored it under another key), so check the
database before you fix the model and flush again: a retry may insert it once more. The check is the library's own record of
reading the row back, so a listener which changes `exists` afterwards does not affect it.

The rows are matched with the stored ones by their unique attributes in PHP first. When the
database finds a row which PHP does not match (e.g. a case-insensitive collation), the
database decides, with one extra SELECT per such row, so many such rows cost extra queries.
`uniqueBy()` must identify one stored row: the library may throw
`Lapaliv\BulkUpsert\Exceptions\BulkIdentifierIsAmbiguous` when it notices several equal rows,
but it does not check every case (e.g. NULL in a nullable unique column).

A model must be bound to the table and the connection of the bulk: otherwise
`Lapaliv\BulkUpsert\Exceptions\BulkModelDoesNotMatch` is thrown when the model is given, before
its chunk and right before each statement, after the listeners. The table and the connection of
the bulk are taken when the bulk is made, so changing its model later does not move the writes.

The upsert tells which rows its insert has created from the database itself, not from the
primary keys or the creation time: a row which another process inserts between the steps of
the upsert is updated with your input, and only the rows created by this call get `created`.
A custom driver tells it by implementing `Lapaliv\BulkUpsert\Contracts\BulkInsertedIndexes`
on its insert result; without it, the library falls back to the primary keys and the creation
time, which such a race can fool.

`create($rows, ignoreConflicts: true)` keeps `INSERT IGNORE` / `INSERT OR IGNORE` /
`ON CONFLICT DO NOTHING`, as before.

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

#### When a flush throws

The accumulated rows are saved chunk by chunk. When the scenario of a chunk throws (a
listener, the database), the exception reaches the caller, and the storage keeps exactly
the rows which still have to be saved:

- the rows of the chunks which have finished leave the storage;
- the chunks which have not run yet stay;
- in the chunk which has thrown, the rows whose statement has run (inserted, updated,
  deleted) leave the storage, the others stay. An upsert row stays until this call has
  either created it or updated it.

So the next `*Accumulated()` call saves the rest without writing a finished row twice.

Keep in mind:

- "has run" means the statement has returned, not that a transaction has been committed.
  When you wrap the bulk into a transaction which rolls back, retry the transaction with
  a new bulk instance and the original input.
- When the connection breaks while a statement is running, the library cannot know
  whether the database has executed it; such a chunk stays in the storage and a retry may
  write it twice.
- A retried row goes through the listeners again. The changes which the listeners made
  before the statement (`saving`, `creating`, `updating`, ...) are not undone, as they
  would not be for a second `Model::save()`. An upsert row is retried with the model it
  had before the insert, so the changes which `created`/`saved` listeners made to the
  model read back from the database are not kept. The events which a failure has
  interrupted (e.g. the `created` events of the other rows of the chunk) are not
  dispatched later.
- A row counts as written once the driver call has returned. If the statement has run
  but the driver or a query listener throws before that, the row is retried and may be
  written twice.
- A forced flush (`*Accumulated()`, `create()`, ...) of the same storage from a listener
  during a flush throws a `LogicException`. `*OrAccumulate()` from a listener only adds the
  rows; they are saved by the next flush.

### Listeners

#### The order of events

The library works chunk by chunk. It fills a chunk first, then runs the whole set of events
for that chunk, writes the rows, and only after that starts collecting the next one. So the
events of two chunks never mix. The chunk size is set by `chunk()` and defaults to 100.

How to read the chains below. An event without `Many` in its name is fired for every model
of the chunk. When two of them stand next to each other, they are fired model by model:
`created → deleted → saved` means the first model gets all three, then the second one does.
A `-Many` event is fired once, right after the group it closes. And when a listener returns
`false`, its row drops out of every step to the right of it.

Creating:

```
saving → savingMany → creating → creatingMany → deleting → deletingMany
    → INSERT
    → created → deleted → saved → createdMany → deletedMany → savedMany
```

Updating:

```
saving → savingMany → updating → updatingMany → deleting → restoring → deletingMany → restoringMany
    → UPDATE
    → updated → deleted → restored → saved → updatedMany → deletedMany → restoredMany → savedMany
```

Upserting: the creating chain for the rows which are absent in the database, then the
updating chain for the whole chunk. Mind that `saving` and `savingMany` run a second time in
the updating chain, for every row of the chunk, including the ones which have just been
created. `updating`, `updated` and `saved` skip those rows, so a created row sees `saving`
twice but `saved` only once.

Deleting:

```
forceDeleting → deleting → forceDeletingMany → deletingMany
    → DELETE, or UPDATE of deleted_at when the row is soft deleted
    → deleted → forceDeleted → deletedMany → forceDeletedMany
```

The `forceDeleting` and `forceDeleted` events belong to `forceDelete()` on a model which
supports soft deleting. For a model without `SoftDeletes`, `delete()` is already a real
`DELETE` and these two do not fire.

The `deleting` and `deleted` events take part in creating and updating as well, because both
of them can write the `deleted_at` column: creating a row with a filled `deleted_at` soft
deletes it right away, and an update which fills that column in soft deletes the row.

#### How listen to events

There are three ways how you can listen events from the library:

#### How listen to events: Bulk Callbacks

```php
use App\Models\User;
use Lapaliv\BulkUpsert\Collections\BulkRowCollection;

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
    ->onCreatingMany(fn(Collection $users, BulkRowCollection $bulkRows) => /* .. */)

    // Runs after creating.
    ->onCreatedMany(fn(Collection $users, BulkRowCollection $bulkRows) => /* .. */)

    // Runs before updating.
    // If the callback returns `false` then these models won't be updated.
    ->onUpdatingMany(fn(Collection $users, BulkRowCollection $bulkRows) => /* .. */)

    // Runs after updating.
    ->onUpdatedMany(fn(Collection $users, BulkRowCollection $bulkRows) => /* .. */)

    // Runs before deleting.
    // If the callback returns `false` then these models won't be deleted,
    // but it doesn't affect the upserting.
    ->onDeletingMany(fn(Collection $users, BulkRowCollection $bulkRows) => /* .. */)

    // Runs before force deleting.
    // If the callback returns `false` then these models won't be deleted.
    ->onForceDeletingMany(fn(Collection $users, BulkRowCollection $bulkRows) => /* .. */)

    // Runs after deleting.
    ->onDeletedMany(fn(Collection $users, BulkRowCollection $bulkRows) => /* .. */)

    // Runs after force deleting.
    ->onForceDeletedMany(fn(Collection $users, BulkRowCollection $bulkRows) => /* .. */)

    // Runs before restoring.
    // If the callback returns `false` then these models won't be restored,
    // but it doesn't affect the upserting.
    ->onRestoringMany(fn(Collection $users, BulkRowCollection $bulkRows) => /* .. */)

    // Runs after restoring.
    ->onRestoredMany(fn(Collection $users, BulkRowCollection $bulkRows) => /* .. */)

    // Runs before upserting.
    // If the callback returns `false` then these models won't be upserting,
    ->onSavingMany(fn(Collection $users, BulkRowCollection $bulkRows) => /* .. */)

    // Runs after upserting.
    ->onSavedMany(fn(Collection $users, BulkRowCollection $bulkRows) => /* .. */)
```

#### How listen to events: Model Callbacks

You also can use model callbacks. They are almost the same. Just remove the prefix `on`.
For example:

```php
use App\Models\User;
use Lapaliv\BulkUpsert\Collections\BulkRowCollection;

User::saving(fn(User $user) => /* .. */);
User::savingMany(
    fn(Collection $users, BulkRowCollection $bulkRows) => /* .. */
);
```

#### How listen to events: Observer

You also can use observers. For example:

```php
namespace App\Observers;

use App\Models\User;
use Lapaliv\BulkUpsert\Collections\BulkRowCollection;

class UserObserver {
    public function creating(User $user) {
        // ..
    }
    
    public function creatingMany(Collection $users, BulkRowCollection $bulkRows) {
        // ..
    }
}
```

#### Example

You can observe the process. The library supports the base eloquent events and
some extra ones which give you access to the whole collection of models.
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
use Lapaliv\BulkUpsert\Collections\BulkRowCollection;
use Lapaliv\BulkUpsert\Entities\BulkRow;

class UserObserver {
    public function savedMany(Collection $users, BulkRowCollection $bulkRows): void {
        $rawComments = [];
        
        $bulkRows->each(
            function(BulkRow $bulkRow) use(&$rawComments): void {
                $comment = $bulkRow->getOriginal();
                $comment['user_id'] = $bulkRow->getModel()->id;
                $rawComments[] = $comment;
            }
        );
        
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
    ->bulk()
    ->uniqueBy(['email'])
    ->upsert($data);
```

In this example you have a chain. After upserting the user, the library will run
`UserObserver::savedMany()` where the code will prepare comments and will upsert them.

## Performance

Compared with v3 (`113e3e2`) on the same PHP 8.4.24, Laravel 12.64.0 and Carbon 3.13.1.
Each run: `create()` of 10 000 rows into an empty table, `update()` of all of them, `delete()`
of 5 000 of them, `upsert()` of 10 000 rows (5 000 existing and 5 000 new), with
`uniqueBy('code')`, rows given as arrays and no listeners. The median of 7 runs of each
version after a warm-up; CLI, OPcache off; MySQL 8.0.46, PostgreSQL 14.24 (both in Docker on
the same machine) and SQLite 3.54.1. The change is the change of the time: a negative one
means v4 takes less time.

In v2 and v3 `create()`, `update()`, `upsert()` and `delete()` did not split the rows by
`chunk()`: all the given rows went in one statement (only the `*OrAccumulate()` methods
split them). v4 fixes it. The first table shows the difference which you get with
`chunk(1000)`: v3 writes all the given rows at once, v4 in packages of at most 1 000 rows.

| Database | Operation | v3 | v4 | Change |
|---|---|---:|---:|---:|
| MySQL | create | 521 ms | 539 ms | +3% |
| MySQL | update | 12 362 ms | 1 427 ms | -88% |
| MySQL | delete | 236 ms | 268 ms | +13% |
| MySQL | upsert | 7 558 ms | 1 128 ms | -85% |
| PostgreSQL | create | 320 ms | 369 ms | +15% |
| PostgreSQL | update | 3 378 ms | 1 097 ms | -68% |
| PostgreSQL | delete | 134 ms | 182 ms | +36% |
| PostgreSQL | upsert | 2 316 ms | 840 ms | -64% |
| SQLite | create | 224 ms | 261 ms | +17% |
| SQLite | update | 7 266 ms | 946 ms | -87% |
| SQLite | delete | 133 ms | 169 ms | +27% |
| SQLite | upsert | 3 576 ms | 731 ms | -80% |

The second table removes that difference: with `chunk(10000)` both versions take the rows in
packages of the same size. It is not an identical set of queries (e.g. the upsert of v4 runs
one more SELECT on MySQL), so it shows the cost of the whole v4 logic, not of the PHP code
alone. Here `create()` and `delete()` of v4 take more time, while `update()` and `upsert()`
take less.

| Database | Operation | v3 | v4 | Change |
|---|---|---:|---:|---:|
| MySQL | create | 473 ms | 539 ms | +14% |
| MySQL | update | 12 492 ms | 4 864 ms | -61% |
| MySQL | delete | 229 ms | 327 ms | +43% |
| MySQL | upsert | 7 742 ms | 1 974 ms | -74% |
| PostgreSQL | create | 322 ms | 368 ms | +14% |
| PostgreSQL | update | 3 366 ms | 2 517 ms | -25% |
| PostgreSQL | delete | 135 ms | 253 ms | +88% |
| PostgreSQL | upsert | 2 552 ms | 1 404 ms | -45% |
| SQLite | create | 218 ms | 271 ms | +24% |
| SQLite | update | 7 276 ms | 2 334 ms | -68% |
| SQLite | delete | 133 ms | 242 ms | +82% |
| SQLite | upsert | 3 625 ms | 1 329 ms | -63% |

The numbers come from one laptop and depend on the hardware, the data and the database
settings. The script, the instructions and the raw results are in [benchmarks](benchmarks/README.md).

## Transactions

The library does not wrap an operation into a transaction for you. Every operation sends its
queries straight to the connection of your model. There are two narrow exceptions, both inside
the insert of an upsert: SQLite runs the first attempt of the insert in a transaction (a
savepoint within yours), and both SQLite and MySQL insert the rows one by one inside a
transaction after a duplicate key. These transactions only make the insert itself atomic;
they never cover the rest of the operation.

This is a deliberate decision, not something which was forgotten. Only you know how a bulk
operation fits into the rest of your work, so only you can decide what has to be atomic. Any
default the library could pick would be wrong for somebody: a transaction which is too wide
holds locks for a long time and blocks other writers, and a transaction which is too narrow
protects nothing.

So the choice is yours. Below are the three usual options.

No transaction at all. Every chunk is written on its own, and if something fails in the
middle, the chunks which were already written stay in the database. This is often good enough
for imports which you can simply run again.

```php
$bulk->uniqueBy('email')->create($data);
```

One transaction per chunk. A failed chunk is rolled back completely, the previous chunks stay.
Locks are held only while one chunk is being written, so other writers are not blocked for
long. This is a good default for large imports.

```php
foreach ($rows as $row) {
    DB::transaction(
        fn () => $bulk->createOrAccumulate([$row])
    );
}

DB::transaction(
    fn () => $bulk->createAccumulated()
);
```

One transaction around everything. Either all the rows are written or none of them are. Keep
in mind that the longer the transaction is, the longer the locks are held, and a very large
transaction may hit the limits of your database.

```php
DB::transaction(
    fn () => $bulk->uniqueBy('email')->create($data)
);
```

Two things are worth remembering whichever option you pick.

Your listeners run inside the same transaction as the queries which fired them. If a listener
writes to the database, that write is a part of your transaction and will be rolled back with
it. If a listener talks to something outside the database, such as a queue or an HTTP API,
that call will not be rolled back, so dispatch it after the transaction has been committed.

The library reads rows back from the database between the queries, for example to fill in the
models before the events ending with `-ed`. Inside a transaction these reads see your own
uncommitted changes, which is usually what you want, but it also means that the isolation
level of your connection affects what your listeners see.

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
    public function disableEvents(?array $events = null): static;
    
    /**
     * Disables the specified event.
     */
    public function disableEvent(string|BulkEventEnum $event): static;
    
    /**
     * Enables the specified events or the all if `$events` is empty.
     * @param string[]|null $events
     */
    public function enableEvents(?array $events = null): static;
    
    /**
     * Enables the specified event.
     */
    public function enableEvent(string|BulkEventEnum $event): static;
    
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
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * The original item from `iterable rows`.
     * @return array|object|stdClass|Model
     */
    public function getOriginal(): mixed
    {
        return $this->original;
    }

    /**
     * Unique fields which were used for upserting.
     * @return string[]
     */
    public function getUnique(): array
    {
        return $this->unique;
    }
}
```

### TODO
* Bulk restoring
* Bulk touching
* Bulk updating without updating timestamps
* Supporting `DB::raw()` as a value
* Support a custom database driver
* Update rows and return the number of updated ones

### Testing

You can check the [actions](https://github.com/lapaliv/laravel-bulk-upsert/actions?query=branch%3Amaster) or run it on your laptop: 

```shell
git clone https://github.com/lapaliv/laravel-bulk-upsert.git
cp .env.example .env
docker-compose up -d
./vendor/bin/phpunit
```
