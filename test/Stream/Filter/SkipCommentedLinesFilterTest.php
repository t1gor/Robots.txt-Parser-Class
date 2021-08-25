<?php declare(strict_types=1);

namespace Stream\Filter;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Stream\Filters\SkipCommentedLinesFilter;

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
}
