#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * Parses a robots.txt and reports what it cost: wall clock, CPU and peak memory. Exits non-zero
 * when the parse runs past --max-seconds, so CI can hold the line on issue #62.
 *
 * The byte limit is off by design - the point is to measure the parser against the whole file,
 * not against the first 500 KiB it would read in production.
 *
 * --max-seconds gates wall clock, which on a shared runner says as much about the machine as the
 * parser: the same commit has parsed 1 GB in 6.0 s and 13.8 s here. --max-ratio gates
 * parse_seconds divided by a synthetic calibration loop timed in the same process, so machine
 * speed cancels and the number is comparable between runs. See CONTRIBUTING.md.
 *
 * Usage: php bin/benchmark.php --file=robots.txt [--max-seconds=60] [--max-ratio=120]
 *        [--memory-limit=512M] [--lookups=1000] [--json=out.json]
 */

require_once __DIR__ . '/cli-helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/** Enough rounds that the best of them is stable to ~2%; see CONTRIBUTING.md. */
const CALIBRATION_ROUNDS = 7;
const CALIBRATION_ITERATIONS = 1500000;

$options = getopt('', ['file:', 'max-seconds::', 'max-ratio::', 'memory-limit::', 'lookups::', 'json::', 'label::']) ?: [];

if (!isset($options['file']) || !is_readable((string) $options['file'])) {
	fwrite(STDERR, "Usage: benchmark.php --file=robots.txt [--max-seconds=60] [--max-ratio=120] [--memory-limit=512M] [--lookups=1000] [--json=path]\n");
	exit(2);
}

$file       = (string) $options['file'];
$maxSeconds = (float) ($options['max-seconds'] ?? 0);
$maxRatio   = (float) ($options['max-ratio'] ?? 0);
$lookups    = (int) ($options['lookups'] ?? 1000);
$label      = (string) ($options['label'] ?? basename($file));

// a cap rather than -1: a parser that starts hoarding should fail here, not swap the runner to death
ini_set('memory_limit', (string) ($options['memory-limit'] ?? '512M'));

// before the file is opened: this must measure the machine, not the parse that follows
$calibration = calibrate();

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
$ratio   = $parsed / max($calibration, 1e-9);

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
	'calibration_seconds' => round($calibration, 4),
	'parse_ratio'      => round($ratio, 1),
	'max_seconds'      => $maxSeconds ?: null,
	'max_ratio'        => $maxRatio ?: null,
	'php'              => PHP_VERSION,
	'over_seconds'     => $maxSeconds > 0 && $parsed > $maxSeconds,
	'over_ratio'       => $maxRatio > 0 && $ratio > $maxRatio,
];

$result['passed'] = !$result['over_seconds'] && !$result['over_ratio'];

printf("%s\n", str_repeat('=', 62));
printf("  %s (%s, PHP %s)\n", $label, human($size), PHP_VERSION);
printf("%s\n", str_repeat('=', 62));
printf("  parse            %8.2f s   (%s MB/s)\n", $result['parse_seconds'], $result['throughput_mb_s']);
printf("  cpu              %8.2f s   (%d%% of %.2f s wall)\n", $result['cpu_seconds'], $result['cpu_percent'], $result['wall_seconds']);
printf("  peak memory      %10s\n", human($result['peak_memory']));
printf("  tree             %s rules across %s user-agents\n", number_format($rules), number_format(count($tree)));
printf("  %s lookups    %8.2f s   (%s/s, %s allowed)\n", number_format($lookups), $result['lookup_seconds'], number_format($result['lookups_per_s']), number_format($allowed));

printf("  calibration      %8.4f s   (machine speed probe, best of %d)\n", $result['calibration_seconds'], CALIBRATION_ROUNDS);
printf("  ratio            %8.1f     (parse / calibration)\n", $result['parse_ratio']);

if ($maxSeconds > 0) {
	printf("  budget           %8.2f s   %s\n", $maxSeconds, $result['over_seconds'] ? 'EXCEEDED' : 'OK');
}

if ($maxRatio > 0) {
	printf("  ratio budget     %8.1f     %s\n", $maxRatio, $result['over_ratio'] ? 'EXCEEDED' : 'OK');
}

if (isset($options['json'])) {
	file_put_contents((string) $options['json'], json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

// one row per run, so a matrix build reads as a single table
if (($summary = getenv('GITHUB_STEP_SUMMARY')) !== false && $summary !== '') {
	file_put_contents($summary, sprintf(
		"| %s | %s | %.2f s | %s MB/s | %.2f s | %s | %.1f | %s | %s | %s |\n",
		$label,
		human($size),
		$result['parse_seconds'],
		$result['throughput_mb_s'],
		$result['cpu_seconds'],
		human($result['peak_memory']),
		$result['parse_ratio'],
		$maxRatio > 0 ? sprintf('%.0f', $maxRatio) : 'n/a',
		$maxSeconds > 0 ? sprintf('%.0f s', $maxSeconds) : 'n/a',
		$result['passed'] ? 'pass' : 'FAIL'
	), FILE_APPEND);
}

if ($result['over_seconds']) {
	fwrite(STDERR, sprintf("\nParsing %s took %.2fs, over the %.2fs budget.\n", $label, $parsed, $maxSeconds));
}

if ($result['over_ratio']) {
	fwrite(STDERR, sprintf(
		"\nParsing %s cost %.1f calibration units, over the %.1f budget (parse %.2fs / calibration %.4fs).\n"
		. "The ratio is machine-independent, so this is a regression in the parser rather than a slow runner.\n",
		$label, $ratio, $maxRatio, $parsed, $calibration
	));
}

if (!$result['passed']) {
	exit(1);
}

/**
 * How fast is the machine this job landed on?
 *
 * A fixed loop over the primitives the parser leans on - preg_match against a short line, explode,
 * trim, an array write - and deliberately none of the library, so that a regression in source/
 * moves the parse time without moving this. That is the whole point: if the probe called the
 * parser, a regression would slow both sides and the ratio would not budge.
 *
 * Best of several rounds rather than a mean: scheduling noise only ever adds time, so the minimum
 * is the cleanest estimate of what this CPU can do.
 */
function calibrate(): float {
	// warm the opcode and compiled-pattern caches, so round one is not the outlier
	calibrationRound(50000);

	$best = INF;

	for ($round = 0; $round < CALIBRATION_ROUNDS; $round++) {
		$best = min($best, calibrationRound(CALIBRATION_ITERATIONS));
	}

	return $best;
}

function calibrationRound(int $iterations): float {
	$lines = [];

	for ($i = 0; $i < 64; $i++) {
		$lines[] = 'Disallow: /section' . $i . '/page-' . ($i * 7) . '.html';
	}

	$pattern = '/^disallow\s*:\s*/isu';
	$sink    = [];
	$start   = hrtime(true);

	for ($i = 0; $i < $iterations; $i++) {
		$line = $lines[$i & 63];

		if (preg_match($pattern, $line) === 1) {
			$parts         = explode(':', $line);
			$sink[$i & 63] = trim($parts[1]);
		}
	}

	return (hrtime(true) - $start) / 1e9;
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
