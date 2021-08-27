<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\UserAgent;

use Psr\Log\LoggerInterface;
use t1gor\RobotsTxtParser\LogsIfAvailableTrait;
use vipnytt\UserAgentParser;

/**
 * @TODO add cache, maybe?
 */
class UserAgentMatcher implements UserAgentMatcherInterface {

	use LogsIfAvailableTrait;

	public function __construct(?LoggerInterface $logger = null) {
		$this->logger = $logger;
	}

	public function getMatching(string $userAgent, array $available = []): string {
		if ($userAgent === '*') {
			return $userAgent;
		}

		$uaParser       = new UserAgentParser($userAgent);
		$userAgentMatch = $uaParser->getMostSpecific($available);

		if (false !== $userAgentMatch) {
			$this->log("Matched {$userAgentMatch} for user agent {$userAgent}");
			return $userAgentMatch;
		}

		$this->log("Failed to match user agent '{$userAgent}', falling back to '*'");
		return '*';
	}
}
