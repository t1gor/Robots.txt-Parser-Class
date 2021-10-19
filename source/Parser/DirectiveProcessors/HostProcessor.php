<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\HostName;

class HostProcessor extends AbstractDirectiveProcessor implements DirectiveProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::HOST;
	}

	public function process(string $line, array & $root, string & $currentUserAgent = '*', string $prevLine = '') {
		$parts = explode(':', $line);
		array_shift($parts);
		$trimmed     = array_map('trim', $parts);
		$entry       = implode(':', $trimmed);

		if (HostName::isValid($entry)) {
			$root[$currentUserAgent][Directive::HOST] = $entry;
			return;
		}

		$this->log(strtr('{directive} with value {faulty} dropped for {useragent} as invalid{ipAddress}', [
			'{directive}' => Directive::HOST,
			'{faulty}'    => $entry,
			'{useragent}' => $currentUserAgent,
			'{ipAddress}' => HostName::isIpAddress($entry) ? ' (IP address is not a valid hostname)' : '',
		]));
	}
}
