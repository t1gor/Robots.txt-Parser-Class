<?php declare(strict_types=1);

namespace Parser\DirectivesProcessors;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\AbstractDirectiveProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\AbstractValidatedValueProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CommentProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\DirectiveProcessorInterface;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\NoIndexProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\RequestRateProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\RobotVersionProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\VisitTimeProcessor;

/**
 * The processors on their own, without a document around them.
 *
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\AbstractDirectiveProcessor
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\AbstractAllowanceProcessor
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\AbstractValidatedValueProcessor
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CommentProcessor
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\NoIndexProcessor
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\RequestRateProcessor
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\RobotVersionProcessor
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\VisitTimeProcessor
 */
class ExtendedDirectiveProcessorsTest extends TestCase {

	/** @return array the tree the processor wrote into */
	private function process(DirectiveProcessorInterface $processor, string $line, array $tree = []): array {
		$userAgent = '*';

		$processor->process($line, $tree, $userAgent);

		return $tree;
	}

	public function provideProcessors(): array {
		return [
			// processor, directive, a line it accepts, what lands in the tree
			'comment'       => [CommentProcessor::class, Directive::COMMENT, 'Comment: be gentle', ['be gentle']],
			'noindex'       => [NoIndexProcessor::class, Directive::NOINDEX, 'Noindex: /drafts', ['/drafts']],
			'request-rate'  => [RequestRateProcessor::class, Directive::REQUEST_RATE, 'Request-rate: 1/300', ['1/5m']],
			'robot-version' => [RobotVersionProcessor::class, Directive::ROBOT_VERSION, 'Robot-version: 2.0', '2.0'],
			'visit-time'    => [VisitTimeProcessor::class, Directive::VISIT_TIME, 'Visit-time: 0600-0845', '0600-0845'],
		];
	}

	/**
	 * @dataProvider provideProcessors
	 */
	public function testDirectiveName(string $class, Directive $directive) {
		$this->assertSame($directive->value, (new $class())->getDirectiveName());
	}

	/**
	 * @dataProvider provideProcessors
	 */
	public function testMatchesItsOwnDirectiveOnly(string $class, Directive $directive, string $line) {
		$processor = new $class();

		$this->assertTrue($processor->matches($line));
		$this->assertTrue($processor->matches(strtolower($line)));
		$this->assertFalse($processor->matches('Disallow: /admin'));
	}

	/**
	 * @dataProvider provideProcessors
	 */
	public function testAValidValueIsStored(string $class, Directive $directive, string $line, array|string $expected) {
		$this->assertSame(['*' => [$directive->value => $expected]], $this->process(new $class(), $line));
	}

	public function provideInvalidLines(): array {
		return [
			'comment with a hash'  => [CommentProcessor::class, 'Comment: half # of it'],
			'empty comment'        => [CommentProcessor::class, 'Comment:'],
			'relative noindex'     => [NoIndexProcessor::class, 'Noindex: drafts'],
			'empty noindex'        => [NoIndexProcessor::class, 'Noindex:'],
			'rate without period'  => [RequestRateProcessor::class, 'Request-rate: 15686'],
			'version in words'     => [RobotVersionProcessor::class, 'Robot-version: two point oh'],
			'hours that cannot be' => [VisitTimeProcessor::class, 'Visit-time: 2500-2600'],
		];
	}

	/**
	 * @dataProvider provideInvalidLines
	 */
	public function testAnInvalidValueIsDroppedAndLogged(string $class, string $line) {
		$log = new Logger(static::class);
		$log->pushHandler($handler = new TestHandler(LogLevel::DEBUG));

		$this->assertSame([], $this->process(new $class($log), $line));
		$this->assertNotEmpty($handler->getRecords(), 'a dropped value has to say so');
	}

	/** A line with nothing after the name has no value at all, rather than a value of the name. */
	public function testALineWithoutAColonHasNoValue() {
		$this->assertSame([], $this->process(new CommentProcessor(), 'Comment'));
	}

	public function testAValueKeepsItsOwnColons() {
		$tree = $this->process(new CommentProcessor(), 'Comment: see https://example.com/robots');

		$this->assertSame(['see https://example.com/robots'], $tree['*'][Directive::COMMENT->value]);
	}

	/** Request-rate and Comment stack up; the rest keep the last value seen. */
	public function testRepeatedValues() {
		$tree = $this->process(new CommentProcessor(), 'Comment: first');
		$tree = $this->process(new CommentProcessor(), 'Comment: second', $tree);

		$this->assertSame(['first', 'second'], $tree['*'][Directive::COMMENT->value]);

		$tree = $this->process(new VisitTimeProcessor(), 'Visit-time: 0600-0845');
		$tree = $this->process(new VisitTimeProcessor(), 'Visit-time: 0100-0200', $tree);

		$this->assertSame('0100-0200', $tree['*'][Directive::VISIT_TIME->value]);
	}

	public function testARepeatedValueIsKeptOnceAndReported() {
		$log = new Logger(static::class);
		$log->pushHandler($handler = new TestHandler(LogLevel::DEBUG));

		$processor = new RequestRateProcessor($log);
		$tree      = $this->process($processor, 'Request-rate: 1/5m');
		$tree      = $this->process($processor, 'Request-rate: 1/300', $tree);

		$this->assertSame(['1/5m'], $tree['*'][Directive::REQUEST_RATE->value]);
		$this->assertTrue(
			$handler->hasRecordThatContains('skipped as already exists', \Monolog\Level::Debug),
			stringifyLogs($handler->getRecords())
		);
	}

	/** Processors are handed the parser's logger, and hand it back. */
	public function testTheLoggerItWasGiven() {
		$log = new Logger(static::class);

		$this->assertSame($log, (new CommentProcessor($log))->getLogger());
		$this->assertNull((new CommentProcessor())->getLogger());
	}

	/** The base class is public, so a directive the enum never heard of must not kill it. */
	public function testAProcessorOfSomeoneElsesDirective() {
		$processor = new class(null) extends AbstractValidatedValueProcessor {
			public function getDirectiveName(): string {
				return 'x-custom';
			}

			protected function normalise(string $value): ?string {
				return $value;
			}
		};

		$this->assertSame(['*' => ['x-custom' => 'hi']], $this->process($processor, 'X-custom: hi'));
	}

	public function testEveryNewProcessorIsAValidatedValueOne() {
		foreach ([CommentProcessor::class, RequestRateProcessor::class, RobotVersionProcessor::class, VisitTimeProcessor::class] as $class) {
			$this->assertInstanceOf(AbstractValidatedValueProcessor::class, new $class());
			$this->assertInstanceOf(AbstractDirectiveProcessor::class, new $class());
		}
	}
}
