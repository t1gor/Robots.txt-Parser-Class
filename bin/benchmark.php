#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * Parses a robots.txt and reports what it cost: wall clock, CPU and peak memory. Exits non-zero
 * when the parse runs past --max-seconds, so CI can hold the line on issue #62.
 *
 * The byte limit is off by design - the point is to measure the parser against the whole file,
 * not against the first 500 KiB it would read in production.
 *
 * Usage: php bin/benchmark.php --file=robots.txt [--max-seconds=60] [--memory-limit=512M] [--lookups=1000] [--json=out.json]
 */

require_once __DIR__ . '/cli-helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\RobotsTxtParser;

$options = getopt('', ['file:', 'max-seconds::', 'memory-limit::', 'lookups::', 'json::', 'label::']) ?: [];

if (!isset($options['file']) || !is_readable((string) $options['file'])) {
	fwrite(STDERR, "Usage: benchmark.php --file=robots.txt [--max-seconds=60] [--memory-limit=512M] [--lookups=1000] [--json=path]\n");
	exit(2);
}

$file       = (string) $options['file'];
$maxSeconds = (float) ($options['max-seconds'] ?? 0);
$lookups    = (int) ($options['lookups'] ?? 1000);
$label      = (string) ($options['label'] ?? basename($file));

// a cap rather than -1: a parser that starts hoarding should fail here, not swap the runner to death
ini_set('memory_limit', (string) ($options['memory-limit'] ?? '512M'));

$size   = (int) filesize($file);
$handle = fopen($file, 'rb');
$parser = new RobotsTxtParser(new Configuration(byteLimit: null));

$before = getrusage();
$start  = hrtime(true);

$parser->setContent($handle);
$tree = $parser->getRules();

$parsed = (hrtime(true) - $start) / 1e9;

// a built tree nobody can query proves nothing
$lookupStart = hrtime(true);
$allowed     = 0;

for ($i = 0; $i < $lookups; $i++) {
	if ($parser->isAllowed('/admin/' . $i . '/' . ($i % 100), 'Googlebot')) {
		$allowed++;
	}
}

$queried = (hrtime(true) - $lookupStart) / 1e9;
$wall    = $parsed + $queried;
$after   = getrusage();
$cpu     = cpuSeconds($after) - cpuSeconds($before);
$rules   = array_sum(array_map('countRules', $tree));

fclose($handle);

$result = [
	'label'            => $label,
	'file_bytes'       => $size,
	'parse_seconds'    => round($parsed, 3),
	'throughput_mb_s'  => round($size / 1024 / 1024 / max($parsed, 1e-9), 2),
	'wall_seconds'     => round($wall, 3),
	'cpu_seconds'      => round($cpu, 3),
	'cpu_percent'      => (int) round(100 * $cpu / max($wall, 1e-9)),
	'peak_memory'      => memory_get_peak_usage(true),
	'user_agents'      => count($tree),
	'rules'            => $rules,
	'lookups'          => $lookups,
	'lookup_seconds'   => round($queried, 3),
	'lookups_per_s'    => $lookups > 0 ? (int) round($lookups / max($queried, 1e-9)) : 0,
	'max_seconds'      => $maxSeconds ?: null,
	'php'              => PHP_VERSION,
	'passed'           => $maxSeconds <= 0 || $parsed <= $maxSeconds,
];

printf("%s\n", str_repeat('=', 62));
printf("  %s (%s, PHP %s)\n", $label, human($size), PHP_VERSION);
printf("%s\n", str_repeat('=', 62));
printf("  parse            %8.2f s   (%s MB/s)\n", $result['parse_seconds'], $result['throughput_mb_s']);
printf("  cpu              %8.2f s   (%d%% of %.2f s wall)\n", $result['cpu_seconds'], $result['cpu_percent'], $result['wall_seconds']);
printf("  peak memory      %10s\n", human($result['peak_memory']));
printf("  tree             %s rules across %s user-agents\n", number_format($rules), number_format(count($tree)));
printf("  %s lookups    %8.2f s   (%s/s, %s allowed)\n", number_format($lookups), $result['lookup_seconds'], number_format($result['lookups_per_s']), number_format($allowed));

if ($maxSeconds > 0) {
	printf("  budget           %8.2f s   %s\n", $maxSeconds, $result['passed'] ? 'OK' : 'EXCEEDED');
}

if (isset($options['json'])) {
	file_put_contents((string) $options['json'], json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

// one row per run, so a matrix build reads as a single table
if (($summary = getenv('GITHUB_STEP_SUMMARY')) !== false && $summary !== '') {
	file_put_contents($summary, sprintf(
		"| %s | %s | %.2f s | %s MB/s | %.2f s | %s | %s | %s |\n",
		$label,
		human($size),
		$result['parse_seconds'],
		$result['throughput_mb_s'],
		$result['cpu_seconds'],
		human($result['peak_memory']),
		$maxSeconds > 0 ? sprintf('%.0f s', $maxSeconds) : 'n/a',
		$result['passed'] ? 'pass' : 'FAIL'
	), FILE_APPEND);
}

if (!$result['passed']) {
	fwrite(STDERR, sprintf("\nParsing %s took %.2fs, over the %.2fs budget.\n", $label, $parsed, $maxSeconds));
	exit(1);
}

function cpuSeconds(array $usage): float {
	return $usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1e6
		+ $usage['ru_stime.tv_sec'] + $usage['ru_stime.tv_usec'] / 1e6;
}

function countRules($directives): int {
	$count = 0;

	foreach ((array) $directives as $value) {
		$count += is_array($value) ? count($value) : 1;
	}

	return $count;
}
