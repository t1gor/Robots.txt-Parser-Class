<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class UserAgentProcessor extends AbstractDirectiveProcessor implements DirectiveProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::USERAGENT;
	}

	public function process(string $line, array & $root, string & $currentUserAgent = '*', string $prevLine = '') {
		$parts = explode(':', $line);
		$newUserAgent = trim($parts[1]);

		if (empty($root) && $newUserAgent === '*') {
			$root[$newUserAgent] = [];
		}

		if ($newUserAgent === $currentUserAgent) {
			$this->log('New useragent is equal to current one, skipping ...');
			return;
		}

		$currentUserAgent = trim($parts[1]);

		if (!isset($root[$currentUserAgent])) {
			$root[$currentUserAgent] = [];
		}

		// if one user-agent is followed by another one - just link them
		if ($this->matches($prevLine)) {
			$prevParts = explode(':', $prevLine);
			$pervLineUserAgent = trim($prevParts[1]);

			$root[$pervLineUserAgent] = & $root[$currentUserAgent];
		}
	}
}
