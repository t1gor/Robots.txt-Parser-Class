<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class CacheDelayProcessor extends AbstractDirectiveProcessor implements DirectiveProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::CACHE_DELAY;
	}

	public function process(string $line, array & $root, string & $currentUserAgent = '*', string $prevLine = '') {
		$parts              = explode(':', $line);
		$filteredCacheDelay = filter_var($parts[1], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

		if (false === $filteredCacheDelay) {
			$this->log('{directive} with value {faulty} dropped as invalid', [
				'{directive}' => Directive::CACHE_DELAY,
				'{faulty}'    => $parts[1],
			]);
			return;
		}

		$root[$currentUserAgent][Directive::CACHE_DELAY] = $filteredCacheDelay;
	}
}
