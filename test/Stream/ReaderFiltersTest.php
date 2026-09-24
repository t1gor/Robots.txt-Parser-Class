<?php declare(strict_types=1);

namespace Stream;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\Stream\Filters\EnsureEndOfLinesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipCommentedLinesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipDirectivesWithInvalidValuesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipEmptyLinesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipEndOfCommentedLineFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipUnsupportedDirectivesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\TrimSpacesLeftFilter;
use t1gor\RobotsTxtParser\Stream\GeneratorBasedReader;

/**
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::filters
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::getReader
 */
class ReaderFiltersTest extends TestCase {

	public function testListsAppliedFiltersInOrder() {
		$reader = GeneratorBasedReader::fromString("User-agent: *\nDisallow: /a\n");

		$this->assertSame([
			EnsureEndOfLinesFilter::NAME,
			SkipCommentedLinesFilter::NAME,
			SkipEndOfCommentedLineFilter::NAME,
			TrimSpacesLeftFilter::NAME,
			SkipUnsupportedDirectivesFilter::NAME,
			SkipDirectivesWithInvalidValuesFilter::NAME,
			SkipEmptyLinesFilter::NAME,
		], $reader->filters());
	}

	public function testExposedThroughTheParser() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /a\n");

		$this->assertContains(SkipEmptyLinesFilter::NAME, $parser->getReader()->filters());
	}

	public function testEncodingFilterIsListedFirst() {
		$reader = GeneratorBasedReader::fromStream(
			fopen(__DIR__ . '/../Fixtures/market-yandex-Windows-1251.txt', 'r')
		);
		$reader->setEncoding('Windows-1251');

		$this->assertSame('convert.iconv.Windows-1251/utf-8', $reader->filters()[0]);
	}

	/** Names are registered process-wide, so only the first reader registers them. */
	public function testSecondReaderAppliesTheSameFilters() {
		$first  = GeneratorBasedReader::fromString("User-agent: *\n");
		$second = GeneratorBasedReader::fromString("User-agent: *\n");

		$this->assertSame($first->filters(), $second->filters());
		$this->assertCount(7, $second->filters());
	}
}
