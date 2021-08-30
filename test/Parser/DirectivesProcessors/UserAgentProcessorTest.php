<?php declare(strict_types=1);

namespace Parser\DirectivesProcessors;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\UserAgentProcessor;

class UserAgentProcessorTest extends TestCase {

	private ?UserAgentProcessor $processor;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->processor = new UserAgentProcessor($log);
	}

	public function tearDown(): void {
		$this->processor = null;
	}

	public function testAddsNewUserAgentSection() {
		$line = 'User-agent: Google';
		$currentAgent = '*';
		$tree = [
			$currentAgent => [],
		];

		$this->processor->process($line, $tree, $currentAgent);

		$this->assertArrayHasKey('Google', $tree);
		$this->assertEquals('Google', $currentAgent);
	}

	public function testLogsIfNotChanged() {
		$line = 'User-agent: Google';
		$currentAgent = 'Google';
		$tree = [
			$currentAgent => [],
		];

		$this->processor->process($line, $tree, $currentAgent);

		$this->assertCount(1, array_keys($tree));

		/** @var TestHandler $handler */
		$handler = $this->processor->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord('New useragent is equal to current one, skipping ...', LogLevel::DEBUG),
			stringifyLogs($handler->getRecords())
		);
	}
}
