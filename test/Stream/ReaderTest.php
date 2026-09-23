<?php declare(strict_types=1);

namespace Stream;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Stream\GeneratorBasedReader;

/**
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::fromStream
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::fromString
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::getContentIterated
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::getContentRaw
 */
class ReaderTest extends TestCase {

	public function testGetContentWiki() {
		$reader = GeneratorBasedReader::fromStream(fopen(__DIR__ . '/../Fixtures/wikipedia-org.txt', 'r'));
		$generator = $reader->getContentIterated();

		foreach ($generator as $line) {
			$this->assertNotEmpty($line);
			$this->assertStringNotContainsString('#', $line);
		}
	}

	public function testGetContentYaMarket() {
		$reader = GeneratorBasedReader::fromStream(fopen(__DIR__ . '/../Fixtures/market-yandex-ru.txt', 'r'));
		$generator = $reader->getContentIterated();

		foreach ($generator as $idx => $line) {
			$this->assertNotEmpty($line);
			$this->assertStringNotContainsString('#', $line);

			switch ($idx) {
				case '329':
					$this->assertStringContainsString('Sitemap', $line);
					break;

				case '330':
					$this->assertStringContainsString('Host', $line);
					break;
			}
		}
	}

	public function testFromStreamRejectsNonResource() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Argument must be a valid resource type. string given.');

		GeneratorBasedReader::fromStream('/not/a/stream');
	}

	public function testFromStreamRejectsNull() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('NULL given');

		GeneratorBasedReader::fromStream(null);
	}

	public function testGetContentRawReturnsTheFilteredStream() {
		$reader = GeneratorBasedReader::fromString("User-agent: *\n# a comment\nDisallow: /admin\n");
		$raw    = $reader->getContentRaw();

		$this->assertStringContainsString('Disallow: /admin', $raw);
		$this->assertStringNotContainsString('# a comment', $raw, 'filters are applied');
	}

	/** Reading twice rewinds, so the content is not consumed by the first call. */
	public function testGetContentRawIsRepeatable() {
		$reader = GeneratorBasedReader::fromString("User-agent: *\nDisallow: /admin\n");

		$this->assertSame($reader->getContentRaw(), $reader->getContentRaw());
	}
}
