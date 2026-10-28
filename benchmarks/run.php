<?php

/**
 * Runs bench.php for two checkouts of the library, round by round, and writes the results
 * to a JSON Lines file.
 *
 * Usage:
 *   php run.php --old=/path/to/old/checkout --new=/path/to/new/checkout
 *       [--drivers=mysql,pgsql,sqlite] [--chunks=1000,10000] [--rounds=8] [--rows=10000]
 *       [--php=php] [--out=results/results.jsonl]
 *
 * The connection of each driver comes from BENCH_<DRIVER>_HOST, _PORT, _DATABASE, _USERNAME,
 * _PASSWORD (e.g. BENCH_MYSQL_HOST); SQLite needs none (BENCH_SQLITE_DATABASE is optional).
 * The first round is a warm-up and is marked as such. The order of the versions alternates
 * from round to round. The runner stops at the first failed run and refuses to write into
 * an existing file: an interrupted run is started again, not resumed.
 */

declare(strict_types=1);

$options = getopt('', ['old:', 'new:', 'drivers::', 'chunks::', 'rounds::', 'rows::', 'php::', 'out::']);
$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(2);
};

$versions = [];

foreach (['old', 'new'] as $name) {
    $path = isset($options[$name]) ? realpath((string) $options[$name]) : false;

    if ($path === false || !is_file($path . '/src/Bulk.php')) {
        $fail("--{$name} must point to a checkout of lapaliv/laravel-bulk-upsert");
    }

    $versions[$name] = $path;
}

$integer = static function (string $value, string $name) use ($fail): int {
    if (preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
        $fail("{$name} must be a positive integer, got \"{$value}\"");
    }

    return (int) $value;
};
$drivers = explode(',', (string) ($options['drivers'] ?? 'mysql,pgsql,sqlite'));
$chunks = array_map(
    static fn (string $value): int => $integer($value, '--chunks'),
    explode(',', (string) ($options['chunks'] ?? '1000,10000'))
);
$rounds = $integer((string) ($options['rounds'] ?? '8'), '--rounds');
$rows = $integer((string) ($options['rows'] ?? '10000'), '--rows');
$php = (string) ($options['php'] ?? PHP_BINARY);
$out = (string) ($options['out'] ?? __DIR__ . '/results/results.jsonl');

if (array_diff($drivers, ['mysql', 'pgsql', 'sqlite']) !== [] || count($drivers) !== count(array_unique($drivers))) {
    $fail('--drivers must be a list of distinct values of mysql, pgsql, sqlite');
}

if (count($chunks) !== count(array_unique($chunks))) {
    $fail('--chunks must not repeat a value');
}

if ($rounds < 2 || $rows < 2) {
    $fail('--rounds must be at least 2 (one warm-up and one measurement), --rows at least 2');
}

if (file_exists($out)) {
    $fail("{$out} exists: remove it first");
}

if (!is_dir(dirname($out)) && !mkdir(dirname($out), 0777, true)) {
    $fail('Cannot create the directory of ' . $out);
}

/**
 * Run a command and give back its exit code, stdout and stderr.
 *
 * stderr goes to a temporary file, so a large output on it cannot block the process.
 *
 * @param list<string> $command
 * @param array<string, string>|null $env
 *
 * @return array{int, string, string}
 */
$run = static function (array $command, ?array $env = null) use ($fail): array {
    $stderr = tmpfile();
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => $stderr], $pipes, __DIR__, $env);

    if (!is_resource($process)) {
        $fail('Cannot start ' . $command[0]);
    }

    $stdout = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $code = proc_close($process);
    rewind($stderr);
    $error = (string) stream_get_contents($stderr);
    fclose($stderr);

    return [$code, $stdout, $error];
};

[$code, $version] = $run([$php, '-r', 'echo PHP_VERSION;']);

if ($code !== 0) {
    $fail("Cannot run {$php}");
}

$refs = [];

foreach ($versions as $name => $path) {
    [$code, $ref] = $run(['git', '-C', $path, 'rev-parse', '--short', 'HEAD']);
    $refs[$name] = $code === 0 ? trim($ref) : 'unknown';
    [$code, $changes] = $run(['git', '-C', $path, 'status', '--porcelain', '--', 'src']);

    // The measured code must be the one of the commit, or the ref in the results is a lie.
    if ($code === 0 && trim($changes) !== '') {
        $fail("{$path}/src has uncommitted changes: the results would not match {$refs[$name]}");
    }
}

fwrite(STDOUT, "PHP {$version}; old {$refs['old']}, new {$refs['new']}\n");
$baseEnv = getenv();

foreach ($chunks as $chunk) {
    foreach ($drivers as $driver) {
        $prefix = 'BENCH_' . strtoupper($driver) . '_';
        $env = ['BENCH_DB' => $driver] + $baseEnv;

        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $key) {
            $value = getenv($prefix . $key);

            if ($value !== false) {
                $env['BENCH_DB_' . $key] = $value;
            }
        }

        for ($round = 0; $round < $rounds; $round++) {
            $order = $round % 2 === 0 ? ['old', 'new'] : ['new', 'old'];

            foreach ($order as $name) {
                [$code, $stdout, $stderr] = $run([
                    $php,
                    '-d', 'display_errors=stderr',
                    '-d', 'error_reporting=' . (E_ALL & ~E_DEPRECATED),
                    __DIR__ . '/bench.php',
                    '--library=' . $versions[$name],
                    '--rows=' . $rows,
                    '--chunk=' . $chunk,
                    '--ref=' . $refs[$name],
                ], $env);
                $result = json_decode(trim($stdout), true);

                if ($code !== 0 || !is_array($result) || ($result['ok'] ?? false) !== true) {
                    fwrite(STDERR, "FAILED: {$name} {$driver} chunk={$chunk} round={$round} exit={$code}\n");
                    fwrite(STDERR, trim($stdout) . PHP_EOL . trim($stderr) . PHP_EOL);
                    exit(1);
                }

                unset($result['library']);
                // The plan of the whole run, so the summary can tell a missing run.
                $result += [
                    'version' => $name,
                    'round' => $round,
                    'warmup' => $round === 0,
                    'plan' => ['drivers' => $drivers, 'chunks' => $chunks, 'rounds' => $rounds],
                ];

                if (file_put_contents($out, json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
                    $fail("Cannot write to {$out}");
                }

                fwrite(STDOUT, sprintf(
                    "%s %s chunk=%d round=%d %s\n",
                    $name,
                    $driver,
                    $chunk,
                    $round,
                    json_encode($result['times_ms'])
                ));
            }
        }
    }
}
