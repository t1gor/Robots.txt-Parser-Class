<?php declare(strict_types=1);

namespace Parser\DirectivesProcessors;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\SitemapProcessor;

class SitemapProcessorTest extends TestCase {

	private ?SitemapProcessor $processor;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->processor = new SitemapProcessor($log);
	}

	public function tearDown(): void {
		$this->processor = null;
	}

	public function testAddsSitemapDirectiveForDefaultUserAgent() {
		$tree = [];
		$func = $this->processor;
		$line = 'Sitemap: https://www.example.com/sitemap.xml';

		$func($line, $tree);

		$this->assertArrayHasKey('*', $tree);
		$this->assertArrayHasKey(Directive::SITEMAP, $tree['*']);
	}

	public function testAddsSitemapDirectiveForCustomUserAgent() {
		$userAgent = 'Google';
		$tree = [];
		$func = $this->processor;
		$line = 'Sitemap: https://www.example.com/sitemap.xml';

		$func($line, $tree, $userAgent);

		$this->assertArrayHasKey('Google', $tree);
		$this->assertArrayHasKey(Directive::SITEMAP, $tree[$userAgent]);
	}

	public function testAddsSitemapSkipsExistingAndLogsIt() {
		$userAgent = 'Google';
		$tree = [
			$userAgent => [
				Directive::SITEMAP => [
					'https://www.example.com/sitemap.xml'
				]
			]
		];
		$func = $this->processor;
		$line = 'Sitemap: https://www.example.com/sitemap.xml';

		$func($line, $tree, $userAgent);

		$this->assertArrayHasKey('Google', $tree);
		$this->assertArrayHasKey(Directive::SITEMAP, $tree[$userAgent]);

		/** @var TestHandler $handler */
		$handler = $this->processor->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord('sitemap with value https://www.example.com/sitemap.xml skipped as already exists for Google', LogLevel::DEBUG),
			json_encode($handler->getRecords())
		);
	}
}