#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * Reports the cyclomatic complexity Codecov shows, read from the Clover report PHPUnit already
 * writes. Run the suite with coverage first.
 *
 * Usage: php bin/complexity.php [--max=<total>] [--method-max=<per method>] [--report=<clover.xml>]
 */

$options = getopt('', ['max::', 'method-max::', 'report::']) ?: [];
$report  = $options['report'] ?? __DIR__ . '/../build/logs/clover.xml';
$perFile = [];
$hotspots = [];

if (!is_readable($report)) {
	fwrite(STDERR, "No Clover report at {$report}. Run the suite with coverage enabled first.\n");
	exit(2);
}

$xml   = new SimpleXMLElement(file_get_contents($report));
$total = 0;

// Clover carries complexity per class and per method only - the totals Codecov shows are summed
foreach ($xml->project->file as $file) {
	$name           = preg_replace('#^.*/(source/)#', '$1', (string) $file['name']);
	$perFile[$name] = 0;

	foreach ($file->class as $class) {
		$perFile[$name] += (int) $class->metrics['complexity'];
	}

	$total += $perFile[$name];

	foreach ($file->line as $line) {
		if ((string) $line['type'] === 'method') {
			$hotspots[$name . '::' . $line['name']] = (int) $line['complexity'];
		}
	}
}

arsort($perFile);
arsort($hotspots);

$methodMax = (int) ($options['method-max'] ?? 5);

printf("Total complexity: %d across %d files\n\n", $total, count($perFile));

echo "Per file:\n";
foreach ($perFile as $name => $complexity) {
	printf("  %4d  %s\n", $complexity, $name);
}

printf("\nMethods over %d:\n", $methodMax);
foreach (array_filter($hotspots, fn (int $c): bool => $c > $methodMax) as $name => $complexity) {
	printf("  %4d  %s\n", $complexity, $name);
}

if (isset($options['max']) && $total > (int) $options['max']) {
	printf("\nTotal complexity %d is over the agreed ceiling of %d.\n", $total, (int) $options['max']);
	exit(1);
}
