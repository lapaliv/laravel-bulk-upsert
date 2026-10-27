# Release Notes

## [4.x]

#### Unreleased
* [[4.0.0]](https://github.com/lapaliv/laravel-bulk-upsert/compare/v3.0.1...v4.0.0)

Requirements
* PHP ^8.4 (was ^8.2); `illuminate/database` ^11.0|^12.0|^13.0.

Changes which may affect your code
* The insert of `upsert()` skips a duplicate key only: NOT NULL, CHECK, foreign key and
  too-long-value errors of new rows reach the caller.
* A new upsert row which is sent but neither created nor found afterwards throws
  `BulkRowsWereNotSaved` (it was lost silently).
* A model bound to another table or connection than the bulk throws `BulkModelDoesNotMatch`:
  when it is given, before its chunk and right before each statement (after the listeners).
  The table and the connection of the bulk are taken when the bulk is made.
* A creation cancelled by `saving`/`creating` (or their `-Many` events) stays cancelled in
  an upsert: the row is not written by its update part either.
* Several stored rows equal to one unique key may throw `BulkIdentifierIsAmbiguous`.
* A row which `update()` does not find gets no `saving`/`saved` events, is not returned and
  touches nothing; a row which `delete()` does not find gets no deleting/deleted events.
* A soft delete writes `updated_at`, as `Model::delete()` does; deleted models get
  `deleted_at` / `exists = false` whether there are listeners or not.
* After a failed flush exactly the unsaved accumulated rows stay; a forced flush of the same
  storage from a listener is rejected.

Fixes
* Unique keys without hash collisions; rows matched by the equality of the database.
* Upsert races: the driver tells which rows its insert has created (`BulkInsertedIndexes`
  for custom drivers), so a row inserted meanwhile is updated with the input.
* Dates in the storage format of the model; date-only casts; optional `CREATED_AT`/`UPDATED_AT`.
* Casts, mutators and written changes of updated models; `selectAndUpdateMany()` honours casts
  and scopes and updates every row once.
* Writes by the primary keys of the scoped rows; read-backs on the writer.
* Changes which `savingMany` makes to clean models are written.
* Touches: only written rows touch; `morphTo` parents of several classes.
* The table prefix of the connection is kept on Laravel 11.


## [2.x]

####  2023-12-10
* [[2.2.0]](https://github.com/lapaliv/laravel-bulk-upsert/compare/2.1.1...2.2.0) SQLite support has been added [#41](https://github.com/lapaliv/laravel-bulk-upsert/pull/41)
####  2023-12-25
* [[2.2.1]](https://github.com/lapaliv/laravel-bulk-upsert/compare/2.2.0...2.2.1) Refactoring the creating scenario [#44](https://github.com/lapaliv/laravel-bulk-upsert/pull/44)
####  2024-01-23
* [[2.2.2]](https://github.com/lapaliv/laravel-bulk-upsert/compare/2.2.1...2.2.2) Fix for issues with syncing the origins for the updating models [#46](https://github.com/lapaliv/laravel-bulk-upsert/issues/46)
####  2024-01-26
* [[2.2.3]](https://github.com/lapaliv/laravel-bulk-upsert/compare/2.2.2...2.2.3) Fix error when row doesn't exist during upsert by @d3radicated in [#47](https://github.com/lapaliv/laravel-bulk-upsert/pull/47)
####  2024-02-05
* [[2.2.4]](https://github.com/lapaliv/laravel-bulk-upsert/compare/2.2.3...2.2.4) Fixed the problem with not clean the storage after accumulating [#48](https://github.com/lapaliv/laravel-bulk-upsert/issues/48), [#49](https://github.com/lapaliv/laravel-bulk-upsert/issues/49), [#50](https://github.com/lapaliv/laravel-bulk-upsert/issues/50)
