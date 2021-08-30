<?php declare(strict_types=1);

namespace Stream\Filter;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Stream\Filters\SkipCommentedLinesFilter;

/**
 * @covers \t1gor\RobotsTxtParser\Stream\Filters\SkipCommentedLinesFilter::filter
 */
class SkipCommentedLinesFilterTest extends TestCase {

	public function setUp(): void {
		parent::setUp();

		stream_filter_register(SkipCommentedLinesFilter::NAME, SkipCommentedLinesFilter::class);
	}

	public function testRegister() {
		$this->assertContains(SkipCommentedLinesFilter::NAME, stream_get_filters());
	}

	public function testFilter() {
		$stream = fopen(__DIR__ . '/../../Fixtures/with-commented-lines.txt','r');

		// apply filter
		stream_filter_append($stream, SkipCommentedLinesFilter::NAME);

		$fstat = fstat($stream);
		$contents = fread($stream, $fstat['size']);

		// check commented not there
		$this->assertStringNotContainsString('# Disallow: /tech', $contents);
		$this->assertStringNotContainsString('# this is a commented line', $contents);
		$this->assertStringNotContainsString('# it should not be in the iterator', $contents);

		fclose($stream);
	}

	public function testFilterLargeSet() {
		$stream = fopen(__DIR__ . '/../../Fixtures/large-commented-lines.txt','r');

		// apply filter
		stream_filter_append($stream, SkipCommentedLinesFilter::NAME);

		$fstat = fstat($stream);
		$contents = fread($stream, $fstat['size']);

		// check commented not there
		$this->assertStringNotContainsString('# Lorem ipsum dolor sit amet,', $contents);

		fclose($stream);
	}

	public function testFilterWithLogger() {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$stream = fopen(__DIR__ . '/../../Fixtures/large-commented-lines.txt','r');

		// apply filter
		stream_filter_append($stream, SkipCommentedLinesFilter::NAME, STREAM_FILTER_READ, ['logger' => $log]);

		$fstat = fstat($stream);
		$contents = fread($stream, $fstat['size']);

		/** @var TestHandler $handler */
		$handler = $log->getHandlers()[0];

		$messagesOnly = array_map(
			function(array $record) { return $record['message']; },
			$handler->getRecords()
		);

		$expected = require __DIR__ . '/../../Fixtures/expected-skipped-lines-log.php';

		$this->assertNotEmpty($contents);
		$this->assertEquals($messagesOnly, $expected);

		fclose($stream);
	}
}
