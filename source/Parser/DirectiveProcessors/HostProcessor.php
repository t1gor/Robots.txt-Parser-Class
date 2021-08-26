<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class HostProcessor extends AbstractInvokableProcessor implements InvokableProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::HOST;
	}

	public function __invoke(string $line, array &$root, string &$currentUserAgent = '*') {
		$parts = explode(':', $line);
		array_shift($parts);
		$trimmed     = array_map('trim', $parts);
		$entry       = implode(':', $trimmed);
		$filtered    = filter_var($entry, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
		$isIpAddress = false !== $filtered && $filtered === filter_var($filtered, FILTER_VALIDATE_IP);

		if (false !== $filtered && !$isIpAddress) {
			$root[$currentUserAgent][Directive::HOST] = $filtered;
			return;
		}

		$this->log(strtr('{directive} with value {faulty} dropped for {useragent} as invalid{ipAddress}', [
			'{directive}' => Directive::HOST,
			'{faulty}'    => $entry,
			'{useragent}' => $currentUserAgent,
			'{ipAddress}' => $isIpAddress ? ' (IP address is not a valid hostname)' : '',
		]));
	}
}
