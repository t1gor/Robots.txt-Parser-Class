<?php declare(strict_types=1);

namespace Stream\Filter;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Stream\Filters\SkipDirectivesWithInvalidValuesFilter;

/**
 * @covers \t1gor\RobotsTxtParser\Stream\Filters\SkipDirectivesWithInvalidValuesFilter::filter
 */
class SkipDirectivesWithInvalidValuesFilterTest  extends TestCase {

	public function setUp(): void {
		parent::setUp();

		stream_filter_register(SkipDirectivesWithInvalidValuesFilter::NAME, SkipDirectivesWithInvalidValuesFilter::class);
	}

	public function testRegister() {
		$this->assertContains(SkipDirectivesWithInvalidValuesFilter::NAME, stream_get_filters());
	}

	/**
	 * @TODO
	 */
	public function testFilter() {
		$stream = fopen(__DIR__ . '/../../Fixtures/with-invalid-request-rate.txt','r');

		// apply filter
		stream_filter_append($stream, SkipDirectivesWithInvalidValuesFilter::NAME);

		$fstat = fstat($stream);
		$contents = fread($stream, $fstat['size']);

		// check other rules are still in place
		$this->assertStringContainsString('Useragent: GoogleBot', $contents);

		// check faulty removed
		$this->assertStringNotContainsString('Crawl-delay: ngfsngdndag', $contents);
//		$this->assertStringNotContainsString('Crawl-delay: 0.vfsbfsb # invalid', $contents);
		$this->assertStringNotContainsString('Request-rate: 100/bgdndgnd # invalid', $contents);
		$this->assertStringNotContainsString('Request-rate: 15686 # invalid', $contents);
		$this->assertStringNotContainsString('Request-rate: ngdndganda # invalid', $contents);

		fclose($stream);
	}

	/** What it drops is reported through the logger, when one is passed. */
	public function testDroppedValuesAreLogged() {
		$log = new Logger(static::class);
		$log->pushHandler($handler = new TestHandler(LogLevel::DEBUG));

		$stream = fopen(__DIR__ . '/../../Fixtures/with-invalid-request-rate.txt', 'r');
		stream_filter_append($stream, SkipDirectivesWithInvalidValuesFilter::NAME, STREAM_FILTER_READ, ['logger' => $log]);
		stream_get_contents($stream);
		fclose($stream);

		$this->assertTrue(
			$handler->hasRecordThatContains('dropped as invalid Request-rate value.', LogLevel::DEBUG),
			stringifyLogs($handler->getRecords())
		);
		$this->assertTrue(
			$handler->hasRecordThatContains('dropped as invalid Crawl-delay value.', LogLevel::DEBUG),
			stringifyLogs($handler->getRecords())
		);
	}

	/** Nothing to drop means nothing logged. */
	public function testCleanInputLogsNothing() {
		$log = new Logger(static::class);
		$log->pushHandler($handler = new TestHandler(LogLevel::DEBUG));

		$stream = fopen('php://memory', 'r+');
		fwrite($stream, "User-agent: *\nCrawl-delay: 5\n");
		rewind($stream);
		stream_filter_append($stream, SkipDirectivesWithInvalidValuesFilter::NAME, STREAM_FILTER_READ, ['logger' => $log]);

		$this->assertStringContainsString('Crawl-delay: 5', stream_get_contents($stream));
		$this->assertSame([], $handler->getRecords());

		fclose($stream);
	}

	public function allowanceProvider(): array {
		// line, kept?
		return [
			'absolute path'     => ['Disallow: /admin', true],
			'allow path'        => ['Allow: /a/b', true],
			'empty means all'   => ['Disallow:', true],
			'empty with space'  => ['Disallow: ', true],
			'no leading slash'  => ['Disallow: admin', false],
			'relative'          => ['Allow: ../up', false],
			'inlined directive' => ['Disallow: host: example.com', false],
			'wildcard first'    => ['Disallow: *deny/', false],
		];
	}

	/**
	 * Allow/Disallow values are path patterns starting with "/"; an empty value is legal
	 * and must survive.
	 *
	 * @dataProvider allowanceProvider
	 */
	public function testInvalidAllowanceValuesAreDropped(string $line, bool $kept) {
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, "User-agent: *\n{$line}\n");
		rewind($stream);
		stream_filter_append($stream, SkipDirectivesWithInvalidValuesFilter::NAME, STREAM_FILTER_READ);

		$contents = stream_get_contents($stream);
		fclose($stream);

		$kept
			? $this->assertStringContainsString($line, $contents)
			: $this->assertStringNotContainsString($line, $contents);

		$this->assertStringContainsString('User-agent: *', $contents, 'other lines untouched');
	}

	public function testDroppedAllowanceValuesAreLogged() {
		$log = new Logger(static::class);
		$log->pushHandler($handler = new TestHandler(LogLevel::DEBUG));

		$stream = fopen('php://memory', 'r+');
		fwrite($stream, "User-agent: *\nDisallow: notapath\n");
		rewind($stream);
		stream_filter_append($stream, SkipDirectivesWithInvalidValuesFilter::NAME, STREAM_FILTER_READ, ['logger' => $log]);
		stream_get_contents($stream);
		fclose($stream);

		$this->assertTrue(
			$handler->hasRecordThatContains('dropped as invalid allow/disallow value.', LogLevel::DEBUG),
			stringifyLogs($handler->getRecords())
		);
	}
}
