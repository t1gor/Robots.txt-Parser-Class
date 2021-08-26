<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class SitemapProcessor extends AbstractInvokableProcessor implements InvokableProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::SITEMAP;
	}

	public function __invoke(string $line, array & $root, string & $currentUserAgent = '*') {
		$parts = explode(':', $line);
		array_shift($parts);
		$trimmed = array_map('trim', $parts);
		$entry   = implode(':', $trimmed);

		if (!isset($root[$currentUserAgent][Directive::SITEMAP])) {
			$root[$currentUserAgent][Directive::SITEMAP] = [];
		}

		if (!in_array($entry, $root[$currentUserAgent][Directive::SITEMAP])) {
			$root[$currentUserAgent][Directive::SITEMAP][] = $entry;
		} else {
			$this->log(strtr('{directive} with value {faulty} skipped as already exists for {useragent}', [
				'{directive}' => Directive::SITEMAP,
				'{faulty}'    => $entry,
				'{useragent}' => $currentUserAgent,
			]));
		}
	}
}
