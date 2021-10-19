<?php declare(strict_types=1);

namespace Stream\Filter;

use PHPUnit\Framework\TestCase;
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
}
