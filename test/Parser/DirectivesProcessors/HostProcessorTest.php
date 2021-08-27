<?php declare(strict_types=1);

namespace Parser\DirectivesProcessors;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\HostProcessor;

class HostProcessorTest extends TestCase {

	private ?HostProcessor $processor;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->processor = new HostProcessor($log);
	}

	public function tearDown(): void {
		$this->processor = null;
	}

	public function testAddsHostIfCorrect() {
		$tree = [];
		$line = 'Host: www.example.com';

		$this->processor->process($line, $tree);

		$this->assertArrayHasKey('*', $tree);
		$this->assertArrayHasKey(Directive::HOST, $tree['*']);
		$this->assertContains('www.example.com', $tree['*'], json_encode($tree));
	}

	public function testSkipsAndLogsIfIpAddressPassed() {
		$tree = [];
		$line = 'Host: 192.168.0.1';

		$this->processor->process($line, $tree);

		$this->assertArrayNotHasKey('*', $tree);
		$this->assertArrayNotHasKey(Directive::HOST, $tree);

		/** @var TestHandler $handler */
		$handler = $this->processor->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord(
				'host with value 192.168.0.1 dropped for * as invalid (IP address is not a valid hostname)',
				LogLevel::DEBUG
			),
			stringifyLogs($handler->getRecords())
		);
	}

	public function testSkipsAndLogsIfNotValidHost() {
		$tree = [];
		$line = 'Host: bndgang!!!@#$da12345ngda]]';

		$this->processor->process($line, $tree);

		$this->assertArrayNotHasKey('*', $tree);
		$this->assertArrayNotHasKey(Directive::HOST, $tree);

		/** @var TestHandler $handler */
		$handler = $this->processor->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord(
				'host with value bndgang!!!@#$da12345ngda]] dropped for * as invalid',
				LogLevel::DEBUG
			),
			stringifyLogs($handler->getRecords())
		);
	}
}
