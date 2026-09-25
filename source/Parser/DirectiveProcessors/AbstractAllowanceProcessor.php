<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

abstract class AbstractAllowanceProcessor extends AbstractDirectiveProcessor implements DirectiveProcessorInterface {

	use DeduplicatesEntriesTrait;

	public function process(string $line, array &$root, string &$currentUserAgent = '*', string $prevLine = ''): void {
		// not explode(':')[1]: cutting at the first colon widened "/path:with:colon" to "/path"
		$entry     = $this->value($line);
		$directive = $this->getDirectiveName();

		if (empty($entry)) {
			$this->log(strtr('{directive} with empty value found for {useragent}, skipping', [
				'{directive}' => $directive,
				'{useragent}' => $currentUserAgent,
			]));

			return;
		}

		if (!str_starts_with($entry, '/')) {
			$this->log(strtr('{directive} with invalid value "{faulty}" found for {useragent}, skipping', [
				'{directive}' => $directive,
				'{faulty}'    => $entry,
				'{useragent}' => $currentUserAgent,
			]));

			return;
		}

		if (!isset($root[$currentUserAgent][$directive])) {
			$root[$currentUserAgent][$directive] = [];
		}

		if ($this->isDuplicate($root[$currentUserAgent][$directive], $currentUserAgent, $entry)) {
			$this->log(strtr('{directive} with value {faulty} skipped as already exists for {useragent}', [
				'{directive}' => $directive,
				'{faulty}'    => $entry,
				'{useragent}' => $currentUserAgent,
			]));

			return;
		}

		$root[$currentUserAgent][$directive][] = $entry;
	}
}
