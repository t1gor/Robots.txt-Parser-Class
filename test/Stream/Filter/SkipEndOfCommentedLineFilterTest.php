<?php declare(strict_types=1);

namespace Stream\Filter;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Stream\Filters\SkipEndOfCommentedLineFilter;

/**
 * @covers \t1gor\RobotsTxtParser\Stream\Filters\SkipEndOfCommentedLineFilter::filter
 */
class SkipEndOfCommentedLineFilterTest extends TestCase {

	public function setUp(): void {
		parent::setUp();

		stream_filter_register(SkipEndOfCommentedLineFilter::NAME, SkipEndOfCommentedLineFilter::class);
	}

	public function testRegister() {
		$this->assertContains(SkipEndOfCommentedLineFilter::NAME, stream_get_filters());
	}

	public function testFilter() {
		$stream = fopen(__DIR__ . '/../../Fixtures/with-commented-line-endings.txt','r');

		// apply filter
		stream_filter_append($stream, SkipEndOfCommentedLineFilter::NAME);

		$fstat = fstat($stream);
		$contents = fread($stream, $fstat['size']);

		// check commented not there
		$this->assertStringNotContainsString('# ds', $contents);
		$this->assertStringNotContainsString('# low: /tech', $contents);
		$this->assertStringNotContainsString('#: /tech # ds', $contents);

		// should keep valid entries
		$this->assertStringContainsString('Disallow: /comment-after', $contents);

		fclose($stream);
	}

	public function testFilterWithLogger() {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$stream = fopen(__DIR__ . '/../../Fixtures/with-commented-line-endings.txt','r');

		// apply filter
		stream_filter_append($stream, SkipEndOfCommentedLineFilter::NAME, STREAM_FILTER_READ, ['logger' => $log]);

		// do read
		$lines = [];
		while (!feof($stream)) {
			$lines[] = fgets($stream);
		}

		/** @var TestHandler $handler */
		$handler = $log->getHandlers()[0];

		$this->assertNotEmpty($lines);
		$this->assertTrue(
			$handler->hasRecord('5 char(s) dropped as commented out', LogLevel::DEBUG),
			stringifyLogs($handler->getRecords())
		);
		fclose($stream);
	}
}
