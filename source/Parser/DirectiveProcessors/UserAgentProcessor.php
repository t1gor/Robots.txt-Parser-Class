<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class UserAgentProcessor extends AbstractInvokableProcessor implements InvokableProcessorInterface {

	public function __invoke(string $line, array & $root, string & $currentUserAgent = '*') {
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
	}

	public function getDirectiveName(): string {
		return Directive::USERAGENT;
	}
}
