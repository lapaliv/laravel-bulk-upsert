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
save a collection of your models and use eloquent events such as `creating`, `created`, `updating`,
`updated`, `saving`, `saved` at the same time. And you don't need to prepare the number of fields before.

In simple terms, this library runs something like this:

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

You can install the package via composer:

```bash
composer require lapaliv/laravel-bulk-upsert
```

## Usage

```php
use Lapaliv\BulkUpsert\Contracts\BulkModel;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements BulkModel {
    // Change `private` to `public` of the `registerModelEvent`
    public static function registerModelEvent($event, $callback): void
    {
        parent::registerModelEvent($event, $callback);
    }
}

// ...

$users = [
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Garry', 'email' => 'garry@example.com'],
];

// Inserting
use Lapaliv\BulkUpsert\BulkInsert;
app()->make(BulkInsert::class)->insert(User::class, ['email'], $users);

// Updating
use Lapaliv\BulkUpsert\BulkUpdate;
app()->make(BulkUpdate::class)->update(User::class, $users, ['email']);

// Upserting
use Lapaliv\BulkUpsert\BulkUpsert;
app()->make(BulkUpsert::class)->upsert(User::class, $users, ['email']);
```

## Benchmarks

| Operation      | Number of rows | Min    | Avg    | Median | p90    | p95    | p99    | Max    |
|----------------|----------------|--------|--------|--------|--------|--------|--------|--------|
| Insert         | 1              | 0.0011 | 0.0025 | 0.0014 | 0.0011 | 0.0011 | 0.0011 | 0.0186 |
|                | 2              | 0.0014 | 0.0028 | 0.0018 | 0.0015 | 0.0015 | 0.0015 | 0.0187 |
|                | 3              | 0.0018 | 0.0033 | 0.0020 | 0.0018 | 0.0018 | 0.0018 | 0.0198 |
|                | 5              | 0.0022 | 0.0042 | 0.0026 | 0.0023 | 0.0023 | 0.0023 | 0.0202 |
|                | 10             | 0.0036 | 0.0062 | 0.0040 | 0.0037 | 0.0037 | 0.0037 | 0.0217 |
|                | 20             | 0.0061 | 0.0098 | 0.0070 | 0.0065 | 0.0065 | 0.0065 | 0.0247 |
|                | 50             | 0.0134 | 0.0193 | 0.0156 | 0.0154 | 0.0155 | 0.0156 | 0.0333 |
|                | 100            | 0.0259 | 0.0359 | 0.0298 | 0.0460 | 0.0466 | 0.0468 | 0.0477 |
|                | 200            | 0.0521 | 0.0717 | 0.0722 | 0.0989 | 0.0989 | 0.0989 | 0.0989 |
|                | 500            | 0.1577 | 0.1886 | 0.1927 | 0.2185 | 0.2185 | 0.2185 | 0.2185 |
|                | 1000           | 0.3438 | 0.3887 | 0.3894 | 0.4379 | 0.4379 | 0.4379 | 0.4379 |
| Update         | 1              | 0.0024 | 0.0050 | 0.0029 | 0.0024 | 0.0024 | 0.0024 | 0.0264 |
|                | 2              | 0.0034 | 0.0060 | 0.0040 | 0.0035 | 0.0035 | 0.0035 | 0.0235 |
|                | 3              | 0.0043 | 0.0082 | 0.0051 | 0.0044 | 0.0044 | 0.0044 | 0.0237 |
|                | 5              | 0.0061 | 0.0105 | 0.0071 | 0.0064 | 0.0064 | 0.0064 | 0.0271 |
|                | 10             | 0.0106 | 0.0166 | 0.0125 | 0.0110 | 0.0110 | 0.0110 | 0.0346 |
|                | 20             | 0.0194 | 0.0284 | 0.0274 | 0.0207 | 0.0208 | 0.0209 | 0.0461 |
|                | 50             | 0.0421 | 0.0481 | 0.0464 | 0.0459 | 0.0461 | 0.0464 | 0.0999 |
|                | 100            | 0.0837 | 0.1012 | 0.1052 | 0.1123 | 0.1126 | 0.1140 | 0.1389 |
|                | 200            | 0.1760 | 0.2173 | 0.2195 | 0.2399 | 0.2399 | 0.2399 | 0.2399 |
|                | 500            | 0.5048 | 0.5357 | 0.5228 | 0.5965 | 0.5965 | 0.5965 | 0.5965 |
|                | 1000           | 0.9240 | 1.0041 | 1.0113 | 1.0810 | 1.0810 | 1.0810 | 1.0810 |
| Upsert (50/50) | 1              | 0.0021 | 0.0026 | 0.0023 | 0.0021 | 0.0021 | 0.0021 | 0.0236 |
|                | 2              | 0.0040 | 0.0044 | 0.0043 | 0.0040 | 0.0040 | 0.0040 | 0.0068 |
|                | 3              | 0.0042 | 0.0046 | 0.0045 | 0.0043 | 0.0043 | 0.0043 | 0.0071 |
|                | 5              | 0.0056 | 0.0060 | 0.0059 | 0.0057 | 0.0057 | 0.0057 | 0.0081 |
|                | 10             | 0.0090 | 0.0096 | 0.0095 | 0.0093 | 0.0093 | 0.0093 | 0.0112 |
|                | 20             | 0.0154 | 0.0165 | 0.0163 | 0.0159 | 0.0159 | 0.0160 | 0.0192 |
|                | 50             | 0.0344 | 0.0368 | 0.0362 | 0.0359 | 0.0361 | 0.0361 | 0.0725 |
|                | 100            | 0.0651 | 0.0696 | 0.0690 | 0.0714 | 0.0724 | 0.0773 | 0.1259 |
|                | 200            | 0.1298 | 0.1342 | 0.1332 | 0.1625 | 0.1625 | 0.1625 | 0.1625 |
|                | 500            | 0.3283 | 0.3376 | 0.3364 | 0.3770 | 0.3770 | 0.3770 | 0.3770 |
|                | 1000           | 0.6683 | 0.6965 | 0.6933 | 0.7602 | 0.7602 | 0.7602 | 0.7602 |

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
