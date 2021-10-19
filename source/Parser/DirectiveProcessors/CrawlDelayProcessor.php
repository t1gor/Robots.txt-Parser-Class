<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class CrawlDelayProcessor extends AbstractDirectiveProcessor implements DirectiveProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::CRAWL_DELAY;
	}

	public function process(string $line, array & $root, string & $currentUserAgent = '*', string $prevLine = '') {
		$parts              = explode(':', $line);
		$entry              = trim($parts[1]);
		$filteredCrawlDelay = filter_var($entry, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

		if (false === $filteredCrawlDelay) {
			$this->log(strtr('{directive} with value {faulty} dropped as invalid for {useragent}', [
				'{directive}' => Directive::CRAWL_DELAY,
				'{faulty}'    => $entry,
				'{useragent}' => $currentUserAgent
			]));

			return;
		}

		$root[$currentUserAgent][Directive::CRAWL_DELAY] = $filteredCrawlDelay;
	}
}
