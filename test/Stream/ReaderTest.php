<?php declare(strict_types=1);

namespace Stream;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Stream\GeneratorBasedReader;

class ReaderTest extends TestCase {

	public function testGetContentWiki() {
		$reader = GeneratorBasedReader::fromStream(fopen(__DIR__ . './../Fixtures/wikipedia-org.txt', 'r'));
		$generator = $reader->getContent();

		foreach ($generator as $line) {
			$this->assertNotEmpty($line);
			$this->assertStringNotContainsString('#', $line);
		}
	}

	public function testGetContentYaMarket() {
		$reader = GeneratorBasedReader::fromStream(fopen(__DIR__ . './../Fixtures/market-yandex-ru.txt', 'r'));
		$generator = $reader->getContent();

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
}
