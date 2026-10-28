# Benchmarks

The benchmark compares two checkouts of the library (e.g. v3 and v4) on the same PHP,
Laravel and Carbon. It is not a part of the package: the directory is excluded from the
dist archives (`export-ignore` in `.gitattributes`).

## Scenario

One run, on a new table with a random name (`bench_<random>`):

1. `create()` of 10 000 rows into the empty table;
2. `update()` of all 10 000 rows (every row gets other values);
3. `delete()` of 5 000 rows (the even codes);
4. `upsert()` of 10 000 rows: 5 000 of them exist, 5 000 are new.

All the calls use `uniqueBy('code')` and the given `chunk()`, rows given as arrays, no
listeners. Only the four library calls are timed: generating the rows, creating the table,
the checks and the bootstrap are not. After every step every row of the table is compared
with the expected business values (`code`, `name`, `amount`, `status`) and the number of
rows; a wrong result stops the run. The primary keys and the timestamps are not compared.

## Running

Use a separate test database. A run creates its table and drops only that table, also after
an exception, but a killed process may leave a `bench_*` table behind.

```bash
cd benchmarks
composer install

# Two checkouts of the library, e.g. as worktrees of this repository
git worktree add ../../bulk-v3 master
git worktree add ../../bulk-v4 v4.0.0

export BENCH_MYSQL_HOST=127.0.0.1 BENCH_MYSQL_PORT=3306 BENCH_MYSQL_DATABASE=bench \
       BENCH_MYSQL_USERNAME=bench BENCH_MYSQL_PASSWORD=secret
export BENCH_PGSQL_HOST=127.0.0.1 BENCH_PGSQL_PORT=5432 BENCH_PGSQL_DATABASE=bench \
       BENCH_PGSQL_USERNAME=bench BENCH_PGSQL_PASSWORD=secret
# SQLite uses benchmarks/bench.sqlite unless BENCH_SQLITE_DATABASE is set (a file, not :memory:)

php run.php --old=../../bulk-v3 --new=../../bulk-v4 \
    --drivers=mysql,pgsql,sqlite --chunks=1000,10000 --rounds=8 --rows=10000 \
    --php=php8.4 --out=results/results.jsonl

php summary.php results/results.jsonl --old-label=v3 --new-label=v4
```

The PHP extensions `pdo_mysql`, `pdo_pgsql` and `pdo_sqlite` are needed for the drivers you
run. The first round of every combination is a warm-up and is not counted; the order of the
two versions alternates from round to round; every run is a separate PHP process. The runner
stops at the first failed run and does not write into an existing file. `summary.php`
refuses a file with mixed runtimes, refs or servers, or with missing or duplicated runs.

A single run: `BENCH_DB=sqlite php bench.php --library=../../bulk-v4 --rows=10000 --chunk=1000`.

## Published results

`data/results-v3-v4-php8.4.jsonl` holds the raw results of the tables in the main README:
v3 = `113e3e2` (`master` at that time), v4 = `916b24e`, PHP 8.4.24, the dependencies of
`composer.lock` here. `master` and `v4.0.0` move, so check out these commits to repeat the
published run. The tables are printed by:

```bash
php summary.php data/results-v3-v4-php8.4.jsonl --old-label=v3 --new-label=v4
```

The numbers depend on the machine: they were taken on one laptop (macOS) with MySQL 8.0 and
PostgreSQL 14 in Docker on the same machine, from the CLI with OPcache off. The scenario has
no listeners, casts, touches or concurrent writers; other data and settings may give other
ratios.
