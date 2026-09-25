<?php declare(strict_types=1);

namespace Directives;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\RequestRate;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * Robot-version, Visit-time, Request-rate, Comment and Noindex, end to end.
 *
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\AbstractValidatedValueProcessor
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::getRequestRates
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::getVisitTime
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::getRobotVersion
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::getComments
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::getNoIndex
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::isIndexable
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::forUserAgent
 */
class ExtendedStandardTest extends TestCase {

	protected ?RobotsTxtParser $parser;
	private ?TestHandler $handler;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler($this->handler = new TestHandler(LogLevel::DEBUG));

		$this->parser = (new RobotsTxtParser())->setContent(fopen(__DIR__ . '/../Fixtures/extended-standard.txt', 'r'));
		$this->parser->setLogger($log);
	}

	public function tearDown(): void {
		$this->parser = $this->handler = null;
	}

	public function testRobotVersion() {
		$this->assertSame('2.0', $this->parser->getRobotVersion());
		$this->assertSame('2.0.1', $this->parser->getRobotVersion('GoogleBot'));
	}

	public function testVisitTime() {
		$this->assertSame('0600-0845', (string) $this->parser->getVisitTime());
		$this->assertSame('2300-0200', (string) $this->parser->getVisitTime('GoogleBot'));
	}

	public function testRequestRates() {
		$rates = $this->parser->getRequestRates();

		$this->assertContainsOnlyInstancesOf(RequestRate::class, $rates);
		$this->assertSame(['1/5m 0600-0845', '2/1h 0900-1730'], array_map('strval', $rates));
	}

	/** A rate without a unit is seconds, and comes back written as the unit that fits. */
	public function testRequestRateIsNormalised() {
		$this->assertSame(['1/5m'], array_map('strval', $this->parser->getRequestRates('GoogleBot')));
	}

	public function testComments() {
		$this->assertSame(['regenerated nightly by the CMS'], $this->parser->getComments());
		$this->assertSame(['hello Google'], $this->parser->getComments('GoogleBot'));
	}

	public function testNoIndexPathsAreCollectedAndDeduplicated() {
		$this->assertSame(['/drafts'], $this->parser->getNoIndex('GoogleBot'));
	}

	/** Noindex says nothing about crawling, and Disallow says nothing about indexing. */
	public function testNoIndexIsIndependentOfAllowance() {
		$this->assertFalse($this->parser->isIndexable('/drafts/post-1'));
		$this->assertTrue($this->parser->isAllowed('/drafts/post-1'));

		$this->assertTrue($this->parser->isIndexable('/admin'));
		$this->assertTrue($this->parser->isDisallowed('/admin'));
	}

	public function testIndexableByDefault() {
		$this->assertTrue($this->parser->isIndexable('/anything-else'));
	}

	/** An unknown agent falls back to the catch-all group, as everywhere else. */
	public function testFallsBackToTheCatchAllGroup() {
		$this->assertSame('2.0', $this->parser->getRobotVersion('NeverHeardOfIt'));
		$this->assertSame(['regenerated nightly by the CMS'], $this->parser->getComments('NeverHeardOfIt'));
	}

	/**
	 * @dataProvider provideInvalidValues
	 */
	public function testInvalidValuesAreDropped(Directive $directive) {
		$this->assertArrayNotHasKey($directive->value, $this->parser->getRules('BadBot'));
	}

	public function provideInvalidValues(): array {
		return [
			'version that is not a number' => [Directive::ROBOT_VERSION],
			'hours that do not exist'      => [Directive::VISIT_TIME],
			'rate without a period'        => [Directive::REQUEST_RATE],
			'empty comment'                => [Directive::COMMENT],
			'empty path'                   => [Directive::NOINDEX],
		];
	}

	public function testDroppedValuesAreLogged() {
		$this->parser->getRules();

		$this->assertTrue(
			$this->handler->hasRecordThatContains('robot-version with value "two point oh" dropped as invalid for BadBot', Level::Debug),
			stringifyLogs($this->handler->getRecords())
		);
	}

	/** The spec reads a group without one as 1.0.0 - that default is the caller's `?? '1.0'`, not ours. */
	public function testAGroupWithoutAVersionSaysNothingRatherThanOnePointZero() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\n");

		$this->assertNull($parser->getRobotVersion());
		$this->assertArrayNotHasKey(Directive::ROBOT_VERSION->value, $parser->getRules('*'));
	}

	/** A value that cannot repeat keeps the last one seen, like the delays do. */
	public function testASecondValueOfASingleDirectiveWins() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nVisit-time: 0600-0845\nVisit-time: 0100-0200\n");

		$this->assertSame('0100-0200', (string) $parser->getVisitTime());
	}

	public function testAValueMayHoldColons() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nComment: see https://example.com/robots for why\n");

		$this->assertSame(['see https://example.com/robots for why'], $parser->getComments());
	}

	/** The rest of the line after a "#" is stripped before any directive is read. */
	public function testACommentedCommentKeepsWhatIsLeft() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nComment: keep this # not this\n");

		$this->assertSame(['keep this'], $parser->getComments());
	}

	public function testDuplicateRequestRatesAreKeptOnce() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nRequest-rate: 1/300\nRequest-rate: 1/5m\n");

		$this->assertSame(['1/5m'], array_map('strval', $parser->getRequestRates()));
	}
}
