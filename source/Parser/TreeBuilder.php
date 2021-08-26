<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser;

use t1gor\RobotsTxtParser\Directive;

abstract class TreeBuilder {

	protected static function processMultipleDirectives(string $directive, string $line, array & $root, string $currentUserAgent) {
		$parts = explode(':', $line);
		$entry = trim($parts[1]);

		if (empty($entry)) {
			return;
		}

		if (!isset($root[$currentUserAgent][$directive])) {
			$root[$currentUserAgent][$directive] = [];
		}

		if (!in_array($entry, $root[$currentUserAgent][$directive])) {
			$root[$currentUserAgent][$directive][] = $entry;
		}
	}

	public static function build(\Generator $content): array {
		$root = [];
		$currentUserAgent = '*';

		foreach ($content as $line) {
			switch (true) {
				case preg_match('/^' . Directive::USERAGENT . '\s*:\s+/isu', $line):
					$parts = explode(':', $line);
					$newUserAgent = trim($parts[1]);

					if (empty($root) && $newUserAgent === '*') {
						$root[$newUserAgent] = [];
					}

					if ($newUserAgent !== $currentUserAgent) {
						$currentUserAgent = trim($parts[1]);

						if (!isset($root[$currentUserAgent])) {
							$root[$currentUserAgent] = [];
						}
					}
					break;

				case preg_match('/^' . Directive::CRAWL_DELAY . '\s*:\s+/isu', $line):
					$parts = explode(':', $line);
					$root[$currentUserAgent][Directive::CRAWL_DELAY] = filter_var(
						$parts[1],
						FILTER_SANITIZE_NUMBER_FLOAT,
						FILTER_FLAG_ALLOW_FRACTION
					);
					break;

				case preg_match('/^' . Directive::CACHE_DELAY . '\s*:\s+/isu', $line):
					$parts = explode(':', $line);
					$root[$currentUserAgent][Directive::CACHE_DELAY] = filter_var(
						$parts[1],
						FILTER_SANITIZE_NUMBER_FLOAT,
						FILTER_FLAG_ALLOW_FRACTION
					);
					break;

				case preg_match('/^' . Directive::ALLOW . '\s*:\s+/isu', $line):
					static::processMultipleDirectives(Directive::ALLOW, $line, $root, $currentUserAgent);
					break;

				case preg_match('/^' . Directive::DISALLOW . '\s*:\s+/isu', $line):
					static::processMultipleDirectives(Directive::DISALLOW, $line, $root, $currentUserAgent);
					break;

				case preg_match('/^' . Directive::HOST . '\s*:\s+/isu', $line):
					$parts = explode(':', $line);
					$filtered = filter_var(trim($parts[1]), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);

					if (false !== $filtered) {
						$root[$currentUserAgent][Directive::HOST] = $filtered;
					}
					break;

				case preg_match('/^' . Directive::SITEMAP . '\s*:\s+/isu', $line):
					$parts = explode(':', $line);
					array_shift($parts);
					$trimmed = array_map('trim', $parts);
					$entry = implode(':', $trimmed);

					if (!isset($root[$currentUserAgent][Directive::SITEMAP])) {
						$root[$currentUserAgent][Directive::SITEMAP] = [];
					}

					if (!in_array($entry, $root[$currentUserAgent][Directive::SITEMAP])) {
						$root[$currentUserAgent][Directive::SITEMAP][] = $entry;
					}

					break;

				case preg_match('/^' . Directive::CLEAN_PARAM . '\s*:\s+/isu', $line):
					$parts = explode(':', $line);
					$cleanParams = explode(' ', trim($parts[1]));
					$path = $cleanParams[1] ?? '/*';
					$root[Directive::CLEAN_PARAM][$path] = explode('&', $cleanParams[0]);
					break;
			}
		}

		return $root;
	}
}
