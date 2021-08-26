<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

abstract class AbstractAllowanceProcessor extends AbstractInvokableProcessor implements InvokableProcessorInterface {

	public function __invoke(string $line, array & $root, string & $currentUserAgent = '*') {
		$parts = explode(':', $line);
		$entry = trim($parts[1]);

		if (empty($entry)) {
			return;
		}

		$directive = $this->getDirectiveName();

		if (!isset($root[$currentUserAgent][$directive])) {
			$root[$currentUserAgent][$directive] = [];
		}

		if (!in_array($entry, $root[$currentUserAgent][$directive])) {
			$root[$currentUserAgent][$directive][] = $entry;
		} else {
			$this->log('{directive} with value {faulty} skipped as already exists for {useragent}', [
				'{directive}' => $directive,
				'{faulty}'    => $entry,
				'{useragent}' => $currentUserAgent,
			]);
		}
	}
}
