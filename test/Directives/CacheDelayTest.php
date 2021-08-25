<?php declare(strict_types=1);

namespace Directives;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\RobotsTxtParser;

class CacheDelayTest extends TestCase {

	protected ?RobotsTxtParser $parser;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->parser = new RobotsTxtParser(fopen(__DIR__ . '/../Fixtures/cache-delay-spec.txt', 'r'));
		$this->parser->setLogger($log);
	}

	public function tearDown(): void {
		$this->parser = null;
	}

	public function testCacheDelayForExistingUserAgents() {
		$this->assertEquals(0.5, $this->parser->getDelay('*', Directive::CACHE_DELAY));
		$this->assertEquals(3.7, $this->parser->getDelay('GoogleBot', Directive::CACHE_DELAY));
		$this->assertEquals(8, $this->parser->getDelay('AhrefsBot', Directive::CACHE_DELAY));
	}

	public function testCacheDelayFallsBackForNonStandardCacheDirective() {
		$this->assertEquals(0.5, $this->parser->getDelay('*', Directive::CACHE));
		$this->assertEquals(3.7, $this->parser->getDelay('GoogleBot', Directive::CACHE));
		$this->assertEquals(8, $this->parser->getDelay('AhrefsBot', Directive::CACHE));
	}

	public function testCacheDelayFallsBackToCrawlDelayIfNotSpecified() {
		$this->assertEquals(1.5, $this->parser->getDelay('Yandex', Directive::CACHE));

		/** @var TestHandler $handler */
		$handler = $this->parser->getLogger()->getHandlers()[0];

		$this->assertTrue($handler->hasRecord(
			'cache-delay directive (unofficial): Not found, fallback to crawl-delay directive',
			LogLevel::INFO
		));
	}
}
