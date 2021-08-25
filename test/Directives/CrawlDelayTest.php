<?php declare(strict_types=1);

namespace Directives;

use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use Monolog\Logger;

class CrawlDelayTest extends TestCase {

	protected ?RobotsTxtParser $parser;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->parser = new RobotsTxtParser(fopen(__DIR__ . '/../Fixtures/crawl-delay-spec.txt', 'r'));
		$this->parser->setLogger($log);
	}

	public function tearDown(): void {
		$this->parser = null;
	}

	public function testCrawlDelayForExactUserAgent() {
		$this->assertEquals(0.9, $this->parser->getDelay('GoogleBot'));
		$this->assertEquals(1.5, $this->parser->getDelay('AhrefsBot'));
	}

	public function testCrawlDelayWithNoUserAgent() {
		$this->assertEquals(0, $this->parser->getDelay());
	}

	public function testCrawlDelayLogsFallbackToCrawlDelay() {
		$this->assertEquals(0.9, $this->parser->getDelay('GoogleBot', Directive::CACHE_DELAY));

		/** @var TestHandler $handler */
		$handler = $this->parser->getLogger()->getHandlers()[0];

		$this->assertTrue($handler->hasRecord(
			'cache-delay directive (unofficial): Not found, fallback to crawl-delay directive',
			LogLevel::INFO
		));
	}

	public function testCrawlDelayLogsFallbackForMissingUserAgent() {
		$this->assertEquals(0, $this->parser->getDelay('YandexBot', Directive::CACHE_DELAY));

		/** @var TestHandler $handler */
		$handler = $this->parser->getLogger()->getHandlers()[0];

		$this->assertTrue($handler->hasRecord(
			'cache-delay directive: Not found',
			LogLevel::INFO
		));
	}
}
