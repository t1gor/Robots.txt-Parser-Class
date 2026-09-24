<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class SitemapProcessor extends AbstractDirectiveProcessor implements DirectiveProcessorInterface {

	use DeduplicatesEntriesTrait;

	public function getDirectiveName(): string {
		return Directive::SITEMAP;
	}

	public function process(string $line, array & $root, string & $currentUserAgent = '*', string $prevLine = '') {
		$parts = explode(':', $line);
		array_shift($parts);
		$trimmed = array_map('trim', $parts);
		$entry   = implode(':', $trimmed);

		if (!isset($root[$currentUserAgent][Directive::SITEMAP])) {
			$root[$currentUserAgent][Directive::SITEMAP] = [];
		}

		if ($this->isDuplicate($root[$currentUserAgent][Directive::SITEMAP], $currentUserAgent, $entry)) {
			$this->log(strtr('{directive} with value {faulty} skipped as already exists for {useragent}', [
				'{directive}' => Directive::SITEMAP,
				'{faulty}'    => $entry,
				'{useragent}' => $currentUserAgent,
			]));

			return;
		}

		$root[$currentUserAgent][Directive::SITEMAP][] = $entry;
	}
}
