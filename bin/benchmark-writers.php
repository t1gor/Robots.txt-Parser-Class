#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * What each writer costs on the same tree: wall clock, CPU and peak memory, per size.
 *
 * Both hold the groups - merging the user-agents that share a rule set, and putting the catch-all
 * last, needs every group before the first line goes out. The difference this measures is what
 * happens after that: StringWriter keeps the finished document as well, StreamWriter does not.
 *
 * Output goes to /dev/null by default so the sink costs nothing and cannot distort the memory
 * reading. Point --out somewhere real to include the write.
 *
 * Usage: php bin/benchmark-writers.php [--sizes=S,M,L,XL] [--repeat=3] [--out=/dev/null]
 *        [--encoding=Windows-1251] [--memory-limit=1G] [--json=out.json]
 */

require_once __DIR__ . '/cli-helpers.php';
require_once __DIR__ . '/../vendor/autoload.php';

use t1gor\RobotsTxtParser\Writer\StreamWriter;
use t1gor\RobotsTxtParser\Writer\StringWriter;

/** agents x rules each; the names are what --sizes takes. */
const SIZES = [
	'S'  => ['agents' => 1,   'rules' => 100],
	'M'  => ['agents' => 10,  'rules' => 1000],
	'L'  => ['agents' => 25,  'rules' => 10000],
	'XL' => ['agents' => 50,  'rules' => 20000],
];

$options = getopt('', ['sizes::', 'repeat::', 'out::', 'encoding::', 'memory-limit::', 'json::']) ?: [];
$repeat  = max(1, (int) ($options['repeat'] ?? 3));
$out     = (string) ($options['out'] ?? '/dev/null');
$encoding = isset($options['encoding']) ? (string) $options['encoding'] : null;
$wanted  = array_map('trim', explode(',', strtoupper((string) ($options['sizes'] ?? 'S,M,L,XL'))));
$unknown = array_diff($wanted, array_keys(SIZES));

if ([] !== $unknown) {
	fwrite(STDERR, sprintf("Unknown size(s): %s. Try %s.\n", implode(', ', $unknown), implode(', ', array_keys(SIZES))));
	exit(2);
}

ini_set('memory_limit', (string) ($options['memory-limit'] ?? '1G'));

$results = [];

printf("%s\n", str_repeat('=', 90));
printf("  Writers, %d run(s) each, best kept  (PHP %s, out=%s, encoding=%s)\n", $repeat, PHP_VERSION, $out, $encoding ?? 'UTF-8');
printf("%s\n", str_repeat('=', 90));
printf("  %-4s %-13s %10s %10s %12s %12s %12s\n", 'size', 'writer', 'wall', 'cpu', 'peak MB', 'held MB', 'written MB');
printf("%s\n", str_repeat('-', 90));

foreach ($wanted as $size) {
	$tree = buildTree(SIZES[$size]['agents'], SIZES[$size]['rules']);

	foreach (['StringWriter' => StringWriter::class, 'StreamWriter' => StreamWriter::class] as $name => $class) {
		$best = null;

		for ($run = 0; $run < $repeat; $run++) {
			$measured = measure(new $class(), $tree, $out, $encoding);

			// scheduling noise only ever adds, so the quickest run is the cleanest estimate
			if (null === $best || $measured['wall_seconds'] < $best['wall_seconds']) {
				$best = $measured;
			}
		}

		$results[] = $best + ['size' => $size, 'writer' => $name] + SIZES[$size];

		printf(
			"  %-4s %-13s %9.4fs %9.4fs %12s %12s %12s\n",
			$size,
			$name,
			$best['wall_seconds'],
			$best['cpu_seconds'],
			megabytes($best['peak_memory']),
			megabytes(max($best['held_after'], 0)),
			megabytes($best['bytes'])
		);
	}

	unset($tree);
	gc_collect_cycles();
}

printf("%s\n", str_repeat('-', 90));

foreach ($wanted as $size) {
	$pair = array_values(array_filter($results, static fn (array $r): bool => $r['size'] === $size));
	[$string, $stream] = $pair;

	printf(
		"  %-4s tree %10s MB | stream vs string: %+5.0f%% wall, %+5.0f%% cpu, %10s MB peak (%.1fx)\n",
		$size,
		megabytes($string['resting']),
		100 * ($stream['wall_seconds'] / max($string['wall_seconds'], 1e-9) - 1),
		100 * ($stream['cpu_seconds'] / max($string['cpu_seconds'], 1e-9) - 1),
		megabytes($stream['peak_memory'] - $string['peak_memory']),
		$string['peak_memory'] / max($stream['peak_memory'], 1)
	);
}

if (isset($options['json'])) {
	file_put_contents((string) $options['json'], json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

/** One render, measured on its own. */
function measure(object $writer, array $tree, string $out, ?string $encoding): array {
	$handle = fopen($out, 'wb');

	$writer->setTree($tree)->setEol("\n")->setEncoding($encoding)->setOutput($handle);

	gc_collect_cycles();

	// the tree alone is bigger than either render, so measure what the render adds on top of it
	memory_reset_peak_usage();
	$resting = memory_get_usage();

	$before = getrusage();
	$start  = hrtime(true);
	$bytes  = $writer->render();
	$wall   = (hrtime(true) - $start) / 1e9;
	$cpu    = cpuSeconds(getrusage()) - cpuSeconds($before);

	// not the real_usage figure: that rounds to 2 MB chunks and hides the difference entirely
	$peak = memory_get_peak_usage() - $resting;

	// still alive now the render is over - StringWriter keeps the document, StreamWriter keeps nothing
	$held = memory_get_usage() - $resting;

	fclose($handle);

	return [
		'wall_seconds' => round($wall, 4),
		'cpu_seconds'  => round($cpu, 4),
		'peak_memory'  => $peak,
		'held_after'   => $held,
		'resting'      => $resting,
		'bytes'        => $bytes,
	];
}

/** The shape getRules() returns, without paying for a parse to get it. */
function buildTree(int $agents, int $rules): array {
	$tree = [];

	for ($a = 0; $a < $agents; $a++) {
		$name  = 0 === $a ? '*' : 'Crawler-' . $a;
		$paths = [];

		// distinct per agent on purpose: identical rule sets would merge into one group and the
		// document would stop growing with the tree
		for ($r = 0; $r < $rules; $r++) {
			$paths[] = sprintf('/crawler-%d/section-%d/page-%d/detail-%d', $a, $r % 97, $r, $r * 7 % 1013);
		}

		$tree[$name] = [
			'disallow'    => $paths,
			'allow'       => [sprintf('/section-%d/public', $a)],
			'crawl-delay' => 1 + $a % 5,
		];
	}

	$tree['*']['sitemap']  = ['https://example.com/sitemap.xml', 'https://example.com/sitemap-2.xml'];
	$tree['*']['host']     = 'example.com';
	$tree['clean-param']   = ['/section-1/' => ['sid', 'ref']];

	return $tree;
}
