<?php declare(strict_types=1);

namespace Parser\DirectivesProcessors;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CrawlDelayProcessor;

class CrawlDelayProcessorTest extends TestCase {

	private ?CrawlDelayProcessor $processor;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->processor = new CrawlDelayProcessor($log);
	}

	public function tearDown(): void {
		$this->processor = null;
	}

	public function testSavesValidCrawlDelayInteger() {
		$tree = [];
		$func = $this->processor;
		$line = 'Crawl-delay: 25';

		$func($line, $tree);

		$this->assertArrayHasKey('*', $tree);
		$this->assertArrayHasKey(Directive::CRAWL_DELAY, $tree['*']);
		$this->assertEquals(25, $tree['*'][Directive::CRAWL_DELAY], json_encode($tree));
	}

	public function testSavesValidCrawlDelayDecimal() {
		$tree = [];
		$func = $this->processor;
		$line = 'Crawl-delay: 0.5';

		$func($line, $tree);

		$this->assertArrayHasKey('*', $tree);
		$this->assertArrayHasKey(Directive::CRAWL_DELAY, $tree['*']);
		$this->assertEquals(0.5, $tree['*'][Directive::CRAWL_DELAY], json_encode($tree));
	}

	public function testSkipsInvalidAndLogs() {
		$tree = [];
		$func = $this->processor;
		$line = 'Crawl-delay: thisIsNotANumber';

		$func($line, $tree);

		$this->assertArrayNotHasKey('*', $tree, json_encode($tree));

		/** @var TestHandler $handler */
		$handler = $this->processor->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord(
				'crawl-delay with value thisIsNotANumber dropped as invalid for *',
				LogLevel::DEBUG
			),
			json_encode($handler->getRecords())
		);
	}
}
