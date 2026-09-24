<?php declare(strict_types=1);

namespace Parser\DirectivesProcessors;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CacheDelayProcessor;

/**
 * The sibling of CrawlDelayProcessorTest, which was missing - so everything this processor does
 * was only ever exercised through RobotsTxtParser, whose tests restrict their coverage to a
 * single method and therefore attribute none of it.
 *
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CacheDelayProcessor
 */
class CacheDelayProcessorTest extends TestCase {

	private ?CacheDelayProcessor $processor;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->processor = new CacheDelayProcessor($log);
	}

	public function tearDown(): void {
		$this->processor = null;
	}

	public function testDirectiveName() {
		$this->assertSame(Directive::CACHE_DELAY->value, $this->processor->getDirectiveName());
	}

	public function testMatchesWithAndWithoutWhitespace() {
		$this->assertTrue($this->processor->matches('Cache-delay: 5'));
		$this->assertTrue($this->processor->matches('cache-delay:5'));
		$this->assertFalse($this->processor->matches('Crawl-delay: 5'));
	}

	public function testSavesValidCacheDelayInteger() {
		$tree = [];

		$this->processor->process('Cache-delay: 8', $tree);

		$this->assertArrayHasKey(Directive::CACHE_DELAY->value, $tree['*']);
		$this->assertSame(8.0, $tree['*'][Directive::CACHE_DELAY->value], json_encode($tree));
	}

	public function testSavesValidCacheDelayDecimal() {
		$tree = [];

		$this->processor->process('Cache-delay: 0.5', $tree);

		$this->assertSame(0.5, $tree['*'][Directive::CACHE_DELAY->value], json_encode($tree));
	}

	public function testSavesAgainstTheCurrentUserAgent() {
		$tree      = [];
		$userAgent = 'GoogleBot';

		$this->processor->process('Cache-delay: 3.7', $tree, $userAgent);

		$this->assertSame(3.7, $tree['GoogleBot'][Directive::CACHE_DELAY->value], json_encode($tree));
	}

	public function testValueIsAFloatRatherThanASanitisedString() {
		$tree = [];

		$this->processor->process('Cache-delay: 3.7', $tree);

		// FILTER_SANITIZE_NUMBER_FLOAT used to hand back "3.7" here, unlike crawl-delay
		$this->assertIsFloat($tree['*'][Directive::CACHE_DELAY->value]);
	}

	public function testPaddedValueIsTrimmed() {
		$tree = [];

		$this->processor->process("Cache-delay:   2.5  ", $tree);

		$this->assertSame(2.5, $tree['*'][Directive::CACHE_DELAY->value], json_encode($tree));
	}

	public function testSkipsInvalidAndLogs() {
		$tree = [];

		$this->processor->process('Cache-delay: thisIsNotANumber', $tree);

		// the sanitiser stripped this to "" and stored it; validating drops it
		$this->assertArrayNotHasKey('*', $tree, json_encode($tree));

		/** @var TestHandler $handler */
		$handler = $this->processor->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord(
				'cache-delay with value thisIsNotANumber dropped as invalid for *',
				Level::Debug
			),
			stringifyLogs($handler->getRecords())
		);
	}

	public function testSkipsAnEmptyValue() {
		$tree = [];

		$this->processor->process('Cache-delay:', $tree);

		$this->assertArrayNotHasKey('*', $tree, json_encode($tree));
	}
}
