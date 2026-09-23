<?php declare(strict_types=1);

namespace Stream\Filter;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\Stream\Filters\EnsureEndOfLinesFilter;

/**
 * @covers \t1gor\RobotsTxtParser\Stream\Filters\EnsureEndOfLinesFilter
 */
class EnsureEndOfLinesFilterTest extends TestCase {

	public function eolProvider(): array {
		return [
			'unix'        => ["\n"],
			'windows'     => ["\r\n"],
			'classic mac' => ["\r"],
		];
	}

	/**
	 * @dataProvider eolProvider
	 */
	public function testParsesRegardlessOfLineEnding(string $eol) {
		$parser = new RobotsTxtParser(
			implode($eol, ['User-agent: *', 'Disallow: /tech', 'Allow: /tech/public', ''])
		);

		// a stray CR would end up inside the rule value
		$this->assertSame(['*' => [
			'disallow' => ['/tech'],
			'allow'    => ['/tech/public'],
		]], $parser->getRules());

		$this->assertTrue($parser->isDisallowed('/tech/secret'));
		$this->assertTrue($parser->isAllowed('/tech/public'));
	}

	/**
	 * A CRLF straddling a chunk boundary must stay one break, not become two lines.
	 */
	public function testCrLfSplitAcrossChunks() {
		stream_filter_register(EnsureEndOfLinesFilter::NAME, EnsureEndOfLinesFilter::class);

		$stream = fopen('php://memory', 'r+');

		// "# 12345" is 7 bytes, so the CR ends the first chunk and the LF opens the next
		fwrite($stream, "# 12345\r\nUser-agent: *\r\nDisallow: /tech\r\n");
		rewind($stream);
		stream_set_chunk_size($stream, 8);
		stream_filter_append($stream, EnsureEndOfLinesFilter::NAME, STREAM_FILTER_READ);

		$filtered = stream_get_contents($stream);

		$this->assertSame("# 12345\nUser-agent: *\nDisallow: /tech\n", $filtered);
	}

	/**
	 * CR-only input longer than one stream bucket - the carry-over between chunks
	 * must not lose or merge lines.
	 */
	public function testCrOnlyAcrossManyChunks() {
		$paths = [];
		for ($i = 0; $i < 500; $i++) {
			$paths[] = '/section-' . $i . '/some-fairly-long-path-segment-to-fill-the-buffer';
		}

		$lines = ['User-agent: *'];
		foreach ($paths as $path) {
			$lines[] = 'Disallow: ' . $path;
		}

		$parser = new RobotsTxtParser(implode("\r", $lines) . "\r");
		$rules  = $parser->getRules();

		$this->assertSame($paths, $rules['*']['disallow']);
	}
}
