<?php declare(strict_types=1);

namespace Parser\UserAgent;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Parser\UserAgent\UserAgentMatcher;

/**
 * @covers \t1gor\RobotsTxtParser\Parser\UserAgent\UserAgentMatcher
 */
class UserAgentMatcherTest extends TestCase {

	public function testLogsWhenMatched() {
		$logger = new Logger(static::class);
		$logger->pushHandler(new TestHandler(LogLevel::DEBUG));

		$matcher = new UserAgentMatcher($logger);

		$match = $matcher->getMatching('Google', ['Google']);
		$this->assertEquals('Google', $match);

		$handler = $logger->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord("Matched Google for user agent Google", LogLevel::DEBUG),
			stringifyLogs($handler->getRecords())
		);
	}

	public function testLogsWhenNotMatched() {
		$logger = new Logger(static::class);
		$logger->pushHandler(new TestHandler(LogLevel::DEBUG));

		$matcher = new UserAgentMatcher($logger);

		$match = $matcher->getMatching('Google', []);
		$this->assertEquals('*', $match);

		$handler = $logger->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord("Failed to match user agent 'Google', falling back to '*'", LogLevel::DEBUG),
			stringifyLogs($handler->getRecords())
		);
	}
}
