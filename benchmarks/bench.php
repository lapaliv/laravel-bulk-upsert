<?php

/**
 * One run of the benchmark: create, update, delete, upsert.
 *
 * Usage:
 *   BENCH_DB=mysql BENCH_DB_HOST=127.0.0.1 BENCH_DB_PORT=3306 BENCH_DB_DATABASE=bench \
 *   BENCH_DB_USERNAME=bench BENCH_DB_PASSWORD=secret \
 *   php bench.php --library=/path/to/laravel-bulk-upsert [--rows=10000] [--chunk=1000] [--ref=<label>]
 *
 * The run creates a table with a random name (bench_<random>) and drops only that table at
 * the end, also after a failure. It prints one JSON line to stdout and exits with 1 when an
 * operation fails or leaves a wrong result. Only the four library calls are timed: generating
 * the rows, the schema and the checks of the stored rows are not.
 */

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;

$options = getopt('', ['library:', 'rows::', 'chunk::', 'ref::']);
$library = isset($options['library']) ? realpath((string) $options['library']) : false;
$rows = (string) ($options['rows'] ?? '10000');
$chunk = (string) ($options['chunk'] ?? '1000');

if (preg_match('/^[1-9][0-9]*$/', $rows) !== 1 || preg_match('/^[1-9][0-9]*$/', $chunk) !== 1) {
    fwrite(STDERR, "--rows and --chunk must be positive integers\n");
    exit(2);
}

$rows = (int) $rows;
$chunk = (int) $chunk;
$driver = (string) getenv('BENCH_DB');

if ($library === false || !is_file($library . '/src/Bulk.php')) {
    fwrite(STDERR, "--library must point to a checkout of lapaliv/laravel-bulk-upsert\n");
    exit(2);
}

if (!in_array($driver, ['mysql', 'pgsql', 'sqlite'], true) || $rows < 2) {
    fwrite(STDERR, "Set BENCH_DB to mysql, pgsql or sqlite; --rows must be at least 2\n");
    exit(2);
}

$loader = require __DIR__ . '/vendor/autoload.php';
// The library under test is loaded from the given checkout, the dependencies from this
// directory: both compared versions run on the same Laravel and Carbon.
$loader->addPsr4('Lapaliv\\BulkUpsert\\', $library . '/src/', true);

$env = static function (string $name, string $default = ''): string {
    $value = getenv($name);

    // An empty or a "0" value is kept as it is: it may be a real password.
    return $value === false ? $default : $value;
};
$config = match ($driver) {
    'mysql' => [
        'driver' => 'mysql',
        'host' => $env('BENCH_DB_HOST', '127.0.0.1'),
        'port' => $env('BENCH_DB_PORT', '3306'),
        'database' => $env('BENCH_DB_DATABASE'),
        'username' => $env('BENCH_DB_USERNAME'),
        'password' => $env('BENCH_DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
    ],
    'pgsql' => [
        'driver' => 'pgsql',
        'host' => $env('BENCH_DB_HOST', '127.0.0.1'),
        'port' => $env('BENCH_DB_PORT', '5432'),
        'database' => $env('BENCH_DB_DATABASE'),
        'username' => $env('BENCH_DB_USERNAME'),
        'password' => $env('BENCH_DB_PASSWORD'),
        'charset' => 'utf8',
        'prefix' => '',
        'search_path' => 'public',
    ],
    'sqlite' => [
        'driver' => 'sqlite',
        'database' => $env('BENCH_DB_DATABASE', __DIR__ . '/bench.sqlite'),
        'prefix' => '',
    ],
};

if ($driver === 'sqlite') {
    if ($config['database'] === '' || str_contains($config['database'], ':memory:')) {
        fwrite(STDERR, "Use a file for SQLite: every run is a new process\n");
        exit(2);
    }

    if (!file_exists($config['database']) && !touch($config['database'])) {
        fwrite(STDERR, "Cannot create the SQLite file\n");
        exit(2);
    }
}

$container = new Container();
Container::setInstance($container);
$capsule = new Capsule($container);
$capsule->addConnection($config);
$capsule->setEventDispatcher(new Dispatcher($container));
$capsule->setAsGlobal();
$capsule->bootEloquent();
$container->instance('db', $capsule->getDatabaseManager());
(new Lapaliv\BulkUpsert\Providers\BulkUpsertServiceProvider($container))->register();

/**
 * The benchmarked model; its table gets a random name for every run.
 */
final class BenchItem extends Model
{
    use Lapaliv\BulkUpsert\Bulkable;

    public static string $benchTable = '';

    protected $fillable = ['code', 'name', 'amount', 'status'];

    public function getTable(): string
    {
        return self::$benchTable;
    }
}

$loaded = (string) (new ReflectionClass(Lapaliv\BulkUpsert\Bulk::class))->getFileName();

if (!str_starts_with($loaded, $library . '/src/')) {
    fwrite(STDERR, "The library is loaded from {$loaded}, not from {$library}\n");
    exit(2);
}

$connection = Capsule::connection();
$connection->disableQueryLog();
$schema = $connection->getSchemaBuilder();
$table = 'bench_' . bin2hex(random_bytes(6));

if ($schema->hasTable($table)) {
    fwrite(STDERR, "The table {$table} exists already\n");
    exit(2);
}

BenchItem::$benchTable = $table;

$make = static function (string $version, int $delta) use ($rows): array {
    $result = [];

    for ($i = 1; $i <= $rows; $i++) {
        $result[] = [
            'code' => sprintf('c%06d', $i),
            'name' => "{$version} item {$i}",
            'amount' => $i + $delta,
            'status' => $i % 3 === 0 ? 'archived' : 'active',
        ];
    }

    return $result;
};
$keyed = static function (array $items): array {
    $result = [];

    foreach ($items as $item) {
        $result[$item['code']] = [$item['name'], (int) $item['amount'], $item['status']];
    }

    ksort($result);

    return $result;
};
$created = $make('v1', 0);
$updated = $make('v2', 1000);
$upserted = $make('v3', 2000);
$deleted = array_values(array_map(
    static fn (array $row): array => ['code' => $row['code']],
    array_filter($updated, static fn (array $row): bool => (int) substr($row['code'], 1) % 2 === 0)
));
$kept = array_values(array_filter($updated, static fn (array $row): bool => (int) substr($row['code'], 1) % 2 === 1));
// The rows are compared by code, and their number separately: keying by code would hide
// a duplicated row.
$stored = static function () use ($connection, $table, $keyed): array {
    $items = array_map(
        static fn (object $row): array => (array) $row,
        $connection->table($table)->get(['code', 'name', 'amount', 'status'])->all()
    );

    return ['count' => count($items), 'rows' => $keyed($items)];
};
$expect = static fn (array $items): array => ['count' => count($items), 'rows' => $keyed($items)];
$bulk = static fn () => BenchItem::query()->bulk()->uniqueBy('code')->chunk($chunk);
$measure = static function (callable $action): float {
    $start = hrtime(true);
    $action();

    return round((hrtime(true) - $start) / 1e6, 1);
};

$times = [];
$checks = [];
$error = null;
$created_table = false;
// A wrong result stops the run: the next operation would start from a wrong table.
$check = static function (string $operation, bool $passed) use (&$checks): void {
    $checks[$operation] = $passed;

    if (!$passed) {
        throw new RuntimeException("The table is wrong after {$operation}");
    }
};

try {
    $schema->create($table, function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->string('code')->unique();
        $blueprint->string('name');
        $blueprint->integer('amount');
        $blueprint->string('status', 20);
        $blueprint->timestamps();
    });
    $created_table = true;

    $times['create'] = $measure(fn () => $bulk()->create($created));
    $check('create', $stored() === $expect($created));

    $times['update'] = $measure(fn () => $bulk()->update($updated));
    $check('update', $stored() === $expect($updated));

    $times['delete'] = $measure(fn () => $bulk()->delete($deleted));
    $check('delete', $stored() === $expect($kept));

    $times['upsert'] = $measure(fn () => $bulk()->upsert($upserted));
    $check('upsert', $stored() === $expect($upserted));
} catch (Throwable $exception) {
    $error = $exception::class . ': ' . $exception->getMessage();
} finally {
    // Only the table of this run (its name is random). A create which has failed half-way
    // may have left it, so its existence is checked. A failed drop is reported, without
    // hiding the error of the run. A killed process cannot clean up.
    try {
        $created_table = $created_table || $schema->hasTable($table);
    } catch (Throwable) {
        // The error of the run is already recorded.
    }

    if ($created_table) {
        try {
            $schema->drop($table);
        } catch (Throwable $exception) {
            $error = trim(($error ?? '') . ' | cannot drop ' . $table . ': ' . $exception->getMessage(), ' |');
        }
    }
}

$ok = $error === null && count($checks) === 4 && !in_array(false, $checks, true);

echo json_encode([
    'ok' => $ok,
    'error' => $error,
    'library' => $library,
    'ref' => (string) ($options['ref'] ?? ''),
    'php' => PHP_VERSION,
    'opcache' => (bool) ini_get('opcache.enable_cli'),
    'xdebug' => extension_loaded('xdebug'),
    'laravel' => Composer\InstalledVersions::getPrettyVersion('illuminate/database'),
    'carbon' => Composer\InstalledVersions::getPrettyVersion('nesbot/carbon'),
    'driver' => $driver,
    'server' => (string) $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION),
    'rows' => $rows,
    'chunk' => $chunk,
    'times_ms' => $times,
    'checks' => $checks,
    'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
], JSON_UNESCAPED_SLASHES), PHP_EOL;

exit($ok ? 0 : 1);
