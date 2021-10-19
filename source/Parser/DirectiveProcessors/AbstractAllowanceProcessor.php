<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

abstract class AbstractAllowanceProcessor extends AbstractDirectiveProcessor implements DirectiveProcessorInterface {

	public function process(string $line, array &$root, string &$currentUserAgent = '*', string $prevLine = '') {
		$parts     = explode(':', $line);
		$entry     = trim($parts[1]);
		$directive = $this->getDirectiveName();

		if (empty($entry)) {
			$this->log(strtr('{directive} with empty value found for {useragent}, skipping', [
				'{directive}' => $directive,
				'{useragent}' => $currentUserAgent,
			]));

			return;
		}

		if (!preg_match("/^\//", $entry)) {
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

		if (!in_array($entry, $root[$currentUserAgent][$directive])) {
			$root[$currentUserAgent][$directive][] = $entry;
		} else {
			$this->log(strtr('{directive} with value {faulty} skipped as already exists for {useragent}', [
				'{directive}' => $directive,
				'{faulty}'    => $entry,
				'{useragent}' => $currentUserAgent,
			]));
		}
	}
}
