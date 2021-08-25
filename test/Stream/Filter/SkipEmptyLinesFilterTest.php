<?php declare(strict_types=1);

namespace Stream\Filter;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Stream\Filters\SkipEmptyLinesFilter;

class SkipEmptyLinesFilterTest extends TestCase {

	public function setUp(): void {
		parent::setUp();

		stream_filter_register(SkipEmptyLinesFilter::NAME, SkipEmptyLinesFilter::class);
	}

	public function testRegister() {
		$this->assertContains(SkipEmptyLinesFilter::NAME, stream_get_filters());
	}

	public function testFilter() {
		$beforeLines = 0;
		$afterLines = 0;

		$stream = fopen(__DIR__ . '/../../Fixtures/with-empty-lines.txt','r');

		while (!feof($stream)) {
			fgets($stream);
			$beforeLines++;
		}

		rewind($stream);

		// apply filter
		stream_filter_append($stream, SkipEmptyLinesFilter::NAME);

		$contents = "";

		while (!feof($stream)) {
			$contents .= fgets($stream);
			$afterLines++;
		}

		$this->assertNotEquals("", $contents);
		$this->assertTrue($afterLines < $beforeLines);

		fclose($stream);
	}

	public function testFilterEmptyFirst() {
		$stream = fopen(__DIR__ . '/../../Fixtures/with-empty-lines.txt','r');

		// apply filter
		stream_filter_append($stream, SkipEmptyLinesFilter::NAME);

		$lines = [];

		while (!feof($stream)) {
			$lines[] = fgets($stream);
		}

		$this->assertNotEmpty($lines);
		$this->assertNotEmpty($lines[0]);

		fclose($stream);
	}
}
