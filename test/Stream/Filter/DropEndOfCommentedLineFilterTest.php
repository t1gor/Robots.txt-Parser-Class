<?php declare(strict_types=1);

namespace Stream\Filter;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Stream\Filters\SkipEndOfCommentedLineFilter;

class DropEndOfCommentedLineFilterTest extends TestCase {

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
}
