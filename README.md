## Annotation

How often do you need to create a collection of models? I have this task quite often.
Laravel has a solution for mass create/update/upsert, but it uses a different algorithm than eloquent.
For example, when we are using `$model->create()` we can supplement the data in observers, and it
doesn't work when we use `Model::query()->insert()`.

The second problem is in number of fields. We need to align number of fields before transmitting them
to insert/update/upsert method of the builder. This is not always convenient.

The third problem is in the way get the inserted rows back after `Model::query()->insert()`. 
Laravel doesn't return them. It won't be a big deal if you have only one unique column, but
you will need some time to write quite large SQL query to select them in another case.

Because of the above I have written this library which solves these problems. Use this library you can
save a collection of your models and use eloquent events such as `creating`, `created`, `updating`,
`updated`, `saving`, `saved` at the same time. And you don't need to prepare the number of fields before.

In simple terms, this library makes something like this:

```php
foreach($models as $model){
    $model->save();
}
```

but with 2-3 queries to the database per chunk.

## Features

- Inserting a collection with firing eloquent events such as `creating`, `created`, `saving`, `saved`;
- Updating a collection with firing eloquent events such as `updating`, `updated`, `saving`, `saved`;
- Upserting a collection with firing eloquent events such as `creating`, `created`, `updating`, `updated`, `saving`
  , `saved`;
- Automatically align transmitted fields before save them to the database event if you don't use eloquent events.
- Select inserted rows from the database

## Requires

- MySQL: __5.7+__
- PHP: __8.0+__
- Laravel: __8.0+__

## Installation

#### 1. Composer

Please run this command in your terminal:

```shell
composer require lapaliv/laravel-bulk-upsert
```

#### 2. Contract

Implement `\LapalivBulkUpsert\Contracts\BulkModel` in your model.
You will also need to change the scope from protected to public in some of your model’s methods.

## Examples

### BulkInsert

```php
use Lapaliv\BulkUpsert\BulkInsert;
use Illuminate\Database\Eloquent\Collection;
use App\Models\User;

$users = new Collection([ /* ... */ ]);

app()->make(BulkInsert::class)
    ->chunk(100)
    ->onSaved(
        function(Collection $collection): void {
            // You will get here all your inserted models
        }
    )
    ->insert(User::class, ['email'], $users);
    
    // or
    // ->insertOrIgnore(User::class, ['email'], $users)
```

### BulkUpdate

```php
use Lapaliv\BulkUpsert\BulkUpdate;
use Illuminate\Database\Eloquent\Collection;
use App\Models\User;

$users = new Collection([ /* ... */ ]);

app()->make(BulkUpdate::class)
    ->chunk(100)
    ->onSaved(
        function(Collection $collection): void {
            // You will get here all your updated models
        }
    )
    ->update(User::class, $users);
```

### BulkUpsert

```php
use Lapaliv\BulkUpsert\BulkUpsert;
use Illuminate\Database\Eloquent\Collection;
use App\Models\User;

$users = new Collection([ /* ... */ ]);

app()->make(BulkUpsert::class)
    ->chunk(100)
    ->onSaved(
        function(Collection $collection): void {
            // You will get all your inserted and updated models here
        }
    )
    ->update(User::class, $users);
```

You can see more details [on the wiki page](https://github.com/lapaliv/laravel-bulk-upsert/wiki)
