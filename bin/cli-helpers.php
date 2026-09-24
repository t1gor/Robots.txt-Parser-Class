<?php declare(strict_types=1);

/** Shared by the benchmark scripts in this directory; not part of the library. */

/** Accepts 250MB, 1.5GB, 500K or a plain byte count. */
function parseSize(string $value): int {
	if (!preg_match('/^(\d+(?:\.\d+)?)\s*([KMG]i?B?)?$/i', trim($value), $matches)) {
		fwrite(STDERR, "Cannot read size \"{$value}\". Try 250MB, 1GB or a plain byte count.\n");
		exit(2);
	}

	$units = ['' => 1, 'K' => 1024, 'M' => 1024 ** 2, 'G' => 1024 ** 3];
	$unit  = strtoupper(substr($matches[2] ?? '', 0, 1));

	return (int) round(((float) $matches[1]) * $units[$unit === 'B' ? '' : $unit]);
}

function human(float $bytes): string {
	$units = ['B', 'KB', 'MB', 'GB'];
	$index = 0;

	while ($bytes >= 1024 && $index < count($units) - 1) {
		$bytes /= 1024;
		$index++;
	}

	return sprintf('%.2f %s', $bytes, $units[$index]);
}
