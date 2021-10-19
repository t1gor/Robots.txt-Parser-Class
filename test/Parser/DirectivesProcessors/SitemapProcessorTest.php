<?php declare(strict_types=1);

namespace Parser\DirectivesProcessors;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\SitemapProcessor;

/**
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\SitemapProcessor::process
 */
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
		$line = 'Sitemap: https://www.example.com/sitemap.xml';

		$this->processor->process($line, $tree);

		$this->assertArrayHasKey('*', $tree);
		$this->assertArrayHasKey(Directive::SITEMAP, $tree['*']);
	}

	public function testAddsSitemapDirectiveForCustomUserAgent() {
		$userAgent = 'Google';
		$tree = [];
		$line = 'Sitemap: https://www.example.com/sitemap.xml';

		$this->processor->process($line, $tree, $userAgent);

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
		$line = 'Sitemap: https://www.example.com/sitemap.xml';

		$this->processor->process($line, $tree, $userAgent);

		$this->assertArrayHasKey('Google', $tree);
		$this->assertArrayHasKey(Directive::SITEMAP, $tree[$userAgent]);

		/** @var TestHandler $handler */
		$handler = $this->processor->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord('sitemap with value https://www.example.com/sitemap.xml skipped as already exists for Google', LogLevel::DEBUG),
			stringifyLogs($handler->getRecords())
		);
	}
}
