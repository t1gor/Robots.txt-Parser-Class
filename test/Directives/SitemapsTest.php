<?php declare(strict_types=1);

namespace Directives;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use function Utils\stringifyLogs;

class SitemapsTest extends TestCase {

	protected ?RobotsTxtParser $parser;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->parser = new RobotsTxtParser(fopen(__DIR__ . '/../Fixtures/with-sitemaps.txt', 'r'));
		$this->parser->setLogger($log);
	}

	public function tearDown(): void {
		$this->parser = null;
	}

	public function testRemoveDuplicateSitemaps() {
		$allMaps = $this->parser->getSitemaps();

		$this->assertCount(5, $allMaps);
		$this->assertContains('http://example.com/sitemap.xml?year=2015', $allMaps);
		$this->assertContains('http://somesite.com/sitemap-for-all.xml', $allMaps);
		$this->assertContains('http://internet.com/sitemap-for-google-bot.xml', $allMaps);
		$this->assertContains('http://worldwideweb.com/sitemap-yahoo.xml', $allMaps);
		$this->assertContains('http://example.com/sitemap-yahoo.xml?year=2016', $allMaps);
	}

	public function testGetSitemapForExactUserAgent() {
		$yahooMaps = $this->parser->getSitemaps('Yahoo');

		$this->assertCount(2, $yahooMaps);
		$this->assertContains('http://worldwideweb.com/sitemap-yahoo.xml', $yahooMaps);
		$this->assertContains('http://example.com/sitemap-yahoo.xml?year=2016', $yahooMaps);
	}

	public function testGetSitemapFallsBackToDefault() {
		$fallenBack = $this->parser->getSitemaps('Yandex');

		$this->assertCount(2, $fallenBack);
		$this->assertContains('http://somesite.com/sitemap-for-all.xml', $fallenBack);
		$this->assertContains('http://example.com/sitemap.xml?year=2015', $fallenBack);

		/** @var TestHandler $handler */
		$handler = $this->parser->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord("Failed to match user agent 'Yandex', falling back to '*'", LogLevel::DEBUG),
			stringifyLogs($handler->getRecords())
		);
	}
}
