<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class UserAgentProcessor extends AbstractDirectiveProcessor implements DirectiveProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::USERAGENT->value;
	}

	public function process(string $line, array & $root, string & $currentUserAgent = '*', string $prevLine = ''): void {
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

		// if one user-agent is followed by another one - the new one joins the group of the previous one.
		// Linking the other way round re-bound the previous user-agent, so with three or more user-agent
		// lines the first one was cut off from the group and got none of its rules.
		if ($this->matches($prevLine)) {
			$prevParts = explode(':', $prevLine);
			$pervLineUserAgent = trim($prevParts[1]);

			$root[$currentUserAgent] = & $root[$pervLineUserAgent];
		}
	}
}
