#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * Builds a synthetic robots.txt of a given size for the performance benchmark. Deterministic:
 * same --size and --seed, same bytes. Way too big to keep in the repo, so CI generates them.
 *
 * The bulk of a real oversized robots.txt is not rules - it is HTML, comments and directives
 * nobody supports (see issue #62). So only --density of the lines carry a rule; the rest is junk
 * the parser still has to read through. Raise --density to move the load off the stream filters
 * and onto the tree builder.
 *
 * Usage: php bin/generate-robots.php --size=250MB [--out=robots.txt] [--density=0.02] [--group=100] [--seed=62]
 */

require_once __DIR__ . '/cli-helpers.php';

$options = getopt('', ['size:', 'out::', 'density::', 'group::', 'seed::', 'quiet']) ?: [];

if (!isset($options['size'])) {
	fwrite(STDERR, "Usage: generate-robots.php --size=250MB [--out=path] [--density=0.02] [--group=100] [--seed=62]\n");
	exit(2);
}

$target  = parseSize((string) $options['size']);
$out     = (string) ($options['out'] ?? 'robots.txt');
$density = (float) ($options['density'] ?? 0.02);
$group   = (int) ($options['group'] ?? 100);
$seed    = (int) ($options['seed'] ?? 62);
$quiet   = isset($options['quiet']);

if ($density <= 0 || $density > 1) {
	fwrite(STDERR, "--density must be within (0, 1].\n");
	exit(2);
}

// junk is only junk while the parser skips it: nothing below may be a directive the lib supports
// rules come in one user-agent group at a time, junk fills the gap between groups
$junkPerGroup = max(0, (int) round($group * (1 / $density - 1)));

mt_srand($seed);

$handle = fopen($out, 'wb');

if (false === $handle) {
	fwrite(STDERR, "Cannot write to {$out}.\n");
	exit(1);
}

$crawlers = [
	'Googlebot', 'Googlebot-Image', 'Googlebot-News', 'Bingbot', 'Slurp', 'DuckDuckBot',
	'Baiduspider', 'YandexBot', 'YandexImages', 'Sogou', 'Exabot', 'facebot', 'ia_archiver',
	'AhrefsBot', 'SemrushBot', 'MJ12bot', 'DotBot', 'Applebot', 'PetalBot', 'Bytespider',
	'GPTBot', 'ClaudeBot', 'CCBot', 'Amazonbot', 'archive.org_bot', 'SeznamBot', 'Qwantify',
];

$sections = [
	'admin', 'search', 'cart', 'checkout', 'account', 'private', 'tmp', 'cgi-bin', 'wp-admin',
	'api', 'internal', 'draft', 'preview', 'print', 'feed', 'tag', 'author', 'attachment',
];

$junk = [
	'<!DOCTYPE html>',
	'<html lang="en"><head><meta charset="utf-8">',
	'<title>404 Not Found</title></head>',
	'<body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body>',
	'<script type="text/javascript">window.__CONFIG__ = {"env":"prod","region":"eu-west-1"};</script>',
	'</html>',
	'Nofollow: /this-was-never-a-real-directive',
	'Indexpage: /neither-was-this',
	'Noarchive: /nor-this-one',
	'Acap-crawler: *',
	'Acap-disallow-crawl: /secure',
	'Sitemap-index: https://example.com/sitemaps.xml',
	'',
	'   ',
	"\t",
	'# ------------------------------------------------------------------',
	'# Generated block, harmless filler that still has to be read',
];

$written = 0;
$lines   = 0;
$rules   = 0;
$agents  = 0;
$buffer  = '';
$agentNo = 0;

// $written only moves on flush, so the pending buffer counts towards the target too
while ($written + strlen($buffer) < $target) {
	// a group: one or more user-agents sharing a rule block, the way real files stack them
	$stacked = mt_rand(1, 3);

	for ($i = 0; $i < $stacked; $i++) {
		$name = $crawlers[mt_rand(0, count($crawlers) - 1)] . '/' . (++$agentNo);
		$buffer .= line('User-agent: ' . $name);
		$lines++;
		$agents++;
	}

	for ($i = 0; $i < $group; $i++) {
		$buffer .= line(rule($agentNo, $i, $sections));
		$lines++;
		$rules++;
	}

	$buffer .= line('');
	$lines++;

	for ($i = 0; $i < $junkPerGroup; $i++) {
		$buffer .= line(noise($junk, $sections));
		$lines++;

		// stop mid-junk rather than overshoot the target by a whole group
		if ($written + strlen($buffer) >= $target) {
			break;
		}
	}

	if (strlen($buffer) >= 1 << 20) {
		$written += fwrite($handle, $buffer);
		$buffer  = '';
	}
}

if ('' !== $buffer) {
	$written += fwrite($handle, $buffer);
}

fclose($handle);

if (!$quiet) {
	printf(
		"%s: %s in %s lines, %s rules across %s user-agents (density %.3f)\n",
		$out,
		human($written),
		number_format($lines),
		number_format($rules),
		number_format($agents),
		$rules / max(1, $lines)
	);
}

/** CRLF on a fifth of the lines - the end-of-line filter has to earn its keep. */
function line(string $text): string {
	return $text . (mt_rand(1, 5) === 1 ? "\r\n" : "\n");
}

function rule(int $agentNo, int $index, array $sections): string {
	$section = $sections[$index % count($sections)];
	$path    = sprintf('/%s/%d/%d', $section, $agentNo, $index);

	// one in twelve rules is something other than allow/disallow
	switch ($index % 12) {
		case 3:
			return 'Crawl-delay: ' . mt_rand(1, 30);
		case 7:
			return sprintf('Sitemap: https://example-%d.test/sitemap-%d.xml', $agentNo, $index);
		case 11:
			return sprintf('Clean-param: ref&utm_source /%s/', $section);
	}

	$directive = mt_rand(1, 5) === 1 ? 'Allow' : 'Disallow';

	// sprinkle the pattern syntax the matcher has to handle
	switch (mt_rand(1, 6)) {
		case 1:
			$path .= '/*';
			break;
		case 2:
			$path .= '.html$';
			break;
		case 3:
			$path .= '?id=*&sort=';
			break;
	}

	// trailing comments are common, and the filters have to strip them
	return $directive . ': ' . $path . (mt_rand(1, 10) === 1 ? ' # legacy, review later' : '');
}

function noise(array $junk, array $sections): string {
	$pick = $junk[mt_rand(0, count($junk) - 1)];

	if (mt_rand(1, 4) !== 1) {
		return $pick;
	}

	// the issue calls out long lines: the old parser walked them character by character
	return sprintf(
		'<a href="https://example.test/%s/%d">%s</a>',
		$sections[mt_rand(0, count($sections) - 1)],
		mt_rand(1, 999999),
		str_repeat('lorem ipsum dolor sit amet ', mt_rand(1, 11))
	);
}
