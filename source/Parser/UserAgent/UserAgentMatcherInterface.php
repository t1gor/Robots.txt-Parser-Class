<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\UserAgent;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

interface UserAgentMatcherInterface extends LoggerAwareInterface {

	public function __construct(?LoggerInterface $logger = null);

	public function getMatching(string $userAgent, array $available = []): string;
}
