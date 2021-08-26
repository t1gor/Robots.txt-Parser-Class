<?php declare(strict_types=1);

namespace Stream\Filter;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Stream\Filters\TrimSpacesLeftFilter;

class TrimSpacesLeftAndRightFilterTest extends TestCase {

	public function setUp(): void {
		parent::setUp();

		stream_filter_register(TrimSpacesLeftFilter::NAME, TrimSpacesLeftFilter::class);
	}

	public function testRegister() {
		$this->assertContains(TrimSpacesLeftFilter::NAME, stream_get_filters());
	}

	public function testFilter() {
		$stream = fopen(__DIR__ . '/../../Fixtures/with-empty-and-whitespace.txt', 'r');

		// apply filter
		stream_filter_append($stream, TrimSpacesLeftFilter::NAME);

		$fstat    = fstat($stream);
		$contents = fread($stream, $fstat['size']);

		$this->assertStringNotContainsString('                                        Crawl-Delay: 0.9', $contents);
		$this->assertStringContainsString('Crawl-Delay: 0.9', $contents);
	}
}
