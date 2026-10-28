<?php

/**
 * Prints the medians of a results file as markdown tables, one per chunk size.
 *
 * Usage: php summary.php [results/results.jsonl] [--old-label=old] [--new-label=new]
 *
 * The warm-up round is left out. The change is the change of the time: a negative one means
 * the new version takes less time. The script refuses a file which mixes runtimes (PHP,
 * Laravel, Carbon, OPcache, Xdebug, rows, database servers, refs, plans) or has missing,
 * duplicated or unplanned runs, since its medians would not be comparable.
 */

declare(strict_types=1);

$labels = ['old' => 'old', 'new' => 'new'];
$path = __DIR__ . '/results/results.jsonl';

foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--(old|new)-label=(.+)$/', $argument, $match) === 1) {
        $labels[$match[1]] = $match[2];
    } else {
        $path = $argument;
    }
}

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};
$lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;

if ($lines === false || $lines === []) {
    $fail("No results in {$path}");
}

$results = [];
$seen = [];

foreach ($lines as $number => $line) {
    $result = json_decode($line, true);

    if (!is_array($result) || ($result['ok'] ?? false) !== true) {
        $fail('A failed or broken line ' . ($number + 1) . " in {$path}");
    }

    foreach (['chunk', 'driver', 'round', 'version', 'warmup', 'times_ms', 'plan', 'ref', 'server', 'php', 'laravel', 'carbon', 'rows', 'opcache', 'xdebug'] as $field) {
        if (!array_key_exists($field, $result)) {
            $fail('Line ' . ($number + 1) . " has no {$field}");
        }
    }

    if (!in_array($result['version'], ['old', 'new'], true)
        || !is_int($result['round'])
        || $result['warmup'] !== ($result['round'] === 0)
    ) {
        $fail('Line ' . ($number + 1) . ' has a wrong version, round or warm-up flag');
    }

    foreach (['create', 'update', 'delete', 'upsert'] as $operation) {
        $time = $result['times_ms'][$operation] ?? null;

        if (!is_int($time) && !is_float($time) || $time <= 0) {
            $fail('Line ' . ($number + 1) . " has no valid time of {$operation}");
        }
    }

    $key = implode('|', [$result['chunk'], $result['driver'], $result['round'], $result['version']]);

    if (isset($seen[$key])) {
        $fail("A duplicated run: {$key}");
    }

    $seen[$key] = true;
    $results[] = $result;
}

foreach (['php', 'laravel', 'carbon', 'rows', 'opcache', 'xdebug', 'plan'] as $key) {
    if (count(array_unique(array_map('json_encode', array_column($results, $key)))) !== 1) {
        $fail("The results mix different values of {$key}");
    }
}

// Every planned run must be there exactly once (duplicates are refused above).
$plan = $results[0]['plan'];

if (!is_array($plan['drivers'] ?? null) || !is_array($plan['chunks'] ?? null) || !is_int($plan['rounds'] ?? null) || $plan['rounds'] < 2) {
    $fail('The plan of the run is broken');
}

$expected = count($plan['drivers']) * count($plan['chunks']) * $plan['rounds'] * 2;

if (count($results) !== $expected) {
    $fail(sprintf('%d runs of %d planned: the run is incomplete', count($results), $expected));
}

foreach ($results as $result) {
    if (!in_array($result['driver'], $plan['drivers'], true)
        || !in_array($result['chunk'], $plan['chunks'], true)
        || $result['round'] < 0 || $result['round'] >= $plan['rounds']
        || !in_array($result['version'], ['old', 'new'], true)
    ) {
        $fail('A run outside of the plan');
    }
}

$refs = [];
$servers = [];

foreach ($results as $result) {
    $refs[$result['version']][$result['ref']] = true;
    $servers[$result['driver']][$result['server']] = true;
}

foreach ([...array_values($refs), ...array_values($servers)] as $values) {
    if (count($values) !== 1) {
        $fail('The results mix different refs or database servers');
    }
}

$median = static function (array $values): float {
    sort($values);
    $count = count($values);
    $middle = intdiv($count, 2);

    return $count % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
};
$names = ['mysql' => 'MySQL', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite'];
$first = $results[0];

printf(
    "PHP %s, Laravel %s, Carbon %s, OPcache %s, %d rows; %s = %s, %s = %s\n",
    $first['php'],
    $first['laravel'],
    $first['carbon'],
    $first['opcache'] ? 'on' : 'off',
    $first['rows'],
    $labels['old'],
    array_key_first($refs['old'] ?? ['?' => true]),
    $labels['new'],
    array_key_first($refs['new'] ?? ['?' => true])
);

foreach ($servers as $driver => $values) {
    printf("%s %s\n", $names[$driver] ?? $driver, array_key_first($values));
}

$chunks = array_unique(array_column($results, 'chunk'));
sort($chunks);

foreach ($chunks as $chunk) {
    printf("\n### chunk(%d)\n\n", $chunk);
    printf("| Database | Operation | %s | %s | Change |\n|---|---|---:|---:|---:|\n", $labels['old'], $labels['new']);
    $runs = null;

    foreach (array_keys($names) as $driver) {
        $group = array_filter(
            $results,
            static fn (array $result): bool => $result['chunk'] === $chunk && $result['driver'] === $driver && !$result['warmup']
        );

        if ($group === []) {
            continue;
        }

        $rounds = ['old' => [], 'new' => []];

        foreach ($group as $result) {
            $rounds[$result['version']][] = $result['round'];
        }

        sort($rounds['old']);
        sort($rounds['new']);

        if ($rounds['old'] === [] || $rounds['old'] !== $rounds['new']) {
            $fail("chunk({$chunk}) on {$driver}: the versions do not have the same measured rounds");
        }

        if ($runs !== null && $runs !== count($rounds['old'])) {
            $fail("chunk({$chunk}): the databases do not have the same number of runs");
        }

        $runs = count($rounds['old']);

        foreach (['create', 'update', 'delete', 'upsert'] as $operation) {
            $times = ['old' => [], 'new' => []];

            foreach ($group as $result) {
                $times[$result['version']][] = $result['times_ms'][$operation];
            }

            $old = $median($times['old']);
            $new = $median($times['new']);
            printf(
                "| %s | %s | %s ms | %s ms | %+d%% |\n",
                $names[$driver],
                $operation,
                number_format($old, 0, '.', ' '),
                number_format($new, 0, '.', ' '),
                (int) round(($new / $old - 1) * 100)
            );
        }
    }

    printf("\nThe median of %d runs of each version after a warm-up.\n", (int) $runs);
}
