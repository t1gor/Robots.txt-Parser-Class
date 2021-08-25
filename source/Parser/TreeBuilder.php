<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser;

use t1gor\RobotsTxtParser\Directive;

abstract class TreeBuilder {

	protected static function processMultipleDirectives(string $directive, string $line, array & $root, string $currentUserAgent) {
		$parts = explode(':', $line);

		if (!isset($root[$currentUserAgent][$directive])) {
			$root[$currentUserAgent][$directive] = [];
		}

		$entry = trim(filter_var($parts[1], FILTER_SANITIZE_STRING));

		if (!in_array($entry, $root[$currentUserAgent][$directive])) {
			$root[$currentUserAgent][$directive][] = $entry;
		}
	}

	public static function build(\Generator $content): array {
		$root = [];
		$currentUserAgent = '*';

		foreach ($content as $line) {
			switch (true) {
				case preg_match('/^' . Directive::USERAGENT . ':\s+/isu', $line):
					$parts = explode(':', $line);
					$currentUserAgent = trim($parts[1]);
					$root[$currentUserAgent] = [];
					break;

				case preg_match('/^' . Directive::CRAWL_DELAY . ':\s+/isu', $line):
					$parts = explode(':', $line);
					$root[$currentUserAgent][Directive::CRAWL_DELAY] = filter_var(
						$parts[1],
						FILTER_SANITIZE_NUMBER_FLOAT,
						FILTER_FLAG_ALLOW_FRACTION
					);
					break;

				case preg_match('/^' . Directive::CACHE_DELAY . ':\s+/isu', $line):
					$parts = explode(':', $line);
					$root[$currentUserAgent][Directive::CACHE_DELAY] = filter_var(
						$parts[1],
						FILTER_SANITIZE_NUMBER_FLOAT,
						FILTER_FLAG_ALLOW_FRACTION
					);
					break;

				case preg_match('/^' . Directive::ALLOW . ':\s+/isu', $line):
					static::processMultipleDirectives(Directive::ALLOW, $line, $root, $currentUserAgent);
					break;

				case preg_match('/^' . Directive::DISALLOW . ':\s+/isu', $line):
					static::processMultipleDirectives(Directive::DISALLOW, $line, $root, $currentUserAgent);
					break;

				case preg_match('/^' . Directive::HOST . ':\s+/isu', $line):
					$parts = explode(':', $line);
					$root[$currentUserAgent][Directive::HOST] = filter_var($parts[1], FILTER_SANITIZE_URL);
					break;

				case preg_match('/^' . Directive::SITEMAP . ':\s+/isu', $line):
					static::processMultipleDirectives(Directive::SITEMAP, $line, $root, $currentUserAgent);
					break;
			}
		}

		return $root;
	}
}
