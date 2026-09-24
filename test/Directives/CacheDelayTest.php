<?php declare(strict_types=1);

namespace Directives;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::getDelay
 */
class CacheDelayTest extends TestCase {

	protected ?RobotsTxtParser $parser;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->parser = (new RobotsTxtParser())->setContent(fopen(__DIR__ . '/../Fixtures/cache-delay-spec.txt', 'r'));
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

	public function testCacheDelayIsNormalisedToANumber() {
		// the sanitising filter used to hand back a string here, unlike crawl-delay
		$this->assertIsFloat($this->parser->getDelay('GoogleBot', Directive::CACHE_DELAY));
		$this->assertIsFloat($this->parser->getDelay('AhrefsBot', Directive::CACHE_DELAY));
	}

	public function testInvalidCacheDelayIsDroppedRatherThanStored() {
		$parser = (new RobotsTxtParser())->setContent("User-Agent: *\nCache-Delay: abc\n");

		// sanitising turned this into "" and stored it; validating drops it, so the default stands
		$this->assertSame(0, $parser->getDelay('*', Directive::CACHE_DELAY));
		$this->assertArrayNotHasKey(Directive::CACHE_DELAY, $parser->getRules('*'));
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
			Level::Debug
		));
	}
}
