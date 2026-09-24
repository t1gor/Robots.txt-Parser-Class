<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class CacheDelayProcessor extends AbstractDirectiveProcessor implements DirectiveProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::CACHE_DELAY->value;
	}

	public function process(string $line, array & $root, string & $currentUserAgent = '*', string $prevLine = ''): void {
		$parts = explode(':', $line);
		$entry = trim($parts[1]);

		// VALIDATE, not SANITIZE: the sanitiser never fails, so it turned "abc" into "" and
		// stored that, and returned a string where crawl-delay yields a float
		$filteredCacheDelay = filter_var($entry, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

		if (false === $filteredCacheDelay) {
			$this->log(strtr('{directive} with value {faulty} dropped as invalid for {useragent}', [
				'{directive}' => Directive::CACHE_DELAY->value,
				'{faulty}'    => $entry,
				'{useragent}' => $currentUserAgent,
			]));

			return;
		}

		$root[$currentUserAgent][Directive::CACHE_DELAY->value] = $filteredCacheDelay;
	}
}
