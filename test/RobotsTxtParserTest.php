<?php declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\RobotsTxtParser;

class RobotsTxtParserTest extends TestCase {

	protected ?RobotsTxtParser $parser;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->parser = new RobotsTxtParser(fopen(__DIR__ . '/Fixtures/wikipedia-org.txt', 'r'));
		$this->parser->setLogger($log);
	}

	public function tearDown(): void {
		$this->parser = null;
	}

	public function testGetRulesAll() {
		$rules = $this->parser->getRules();

		// should be all 33 UAs on top level
		$this->assertArrayHasKey("MJ12bot", $rules);
		$this->assertArrayHasKey("Mediapartners-Google*", $rules);
		$this->assertArrayHasKey("IsraBot", $rules);
		$this->assertArrayHasKey("Orthogaffe", $rules);
		$this->assertArrayHasKey("UbiCrawler", $rules);
		$this->assertArrayHasKey("DOC", $rules);
		$this->assertArrayHasKey("Zao", $rules);
		$this->assertArrayHasKey("sitecheck.internetseer.com", $rules);
		$this->assertArrayHasKey("Zealbot", $rules);
		$this->assertArrayHasKey("MSIECrawler", $rules);
		$this->assertArrayHasKey("SiteSnagger", $rules);
		$this->assertArrayHasKey("WebStripper", $rules);
		$this->assertArrayHasKey("WebCopier", $rules);
		$this->assertArrayHasKey("Fetch", $rules);
		$this->assertArrayHasKey("Offline Explorer", $rules);
		$this->assertArrayHasKey("Teleport", $rules);
		$this->assertArrayHasKey("TeleportPro", $rules);
		$this->assertArrayHasKey("WebZIP", $rules);
		$this->assertArrayHasKey("linko", $rules);
		$this->assertArrayHasKey("HTTrack", $rules);
		$this->assertArrayHasKey("Microsoft.URL.Control", $rules);
		$this->assertArrayHasKey("Xenu", $rules);
		$this->assertArrayHasKey("larbin", $rules);
		$this->assertArrayHasKey("libwww", $rules);
		$this->assertArrayHasKey("ZyBORG", $rules);
		$this->assertArrayHasKey("Download Ninja", $rules);
		$this->assertArrayHasKey("fast", $rules);
		$this->assertArrayHasKey("wget", $rules);
		$this->assertArrayHasKey("grub-client", $rules);
		$this->assertArrayHasKey("k2spider", $rules);
		$this->assertArrayHasKey("NPBot", $rules);
		$this->assertArrayHasKey("WebReaper", $rules);
		$this->assertArrayHasKey("*", $rules);
	}

	public function testTreeBuildOnlyOnce() {
		$this->parser->getRules();
		$this->parser->getRules();
		$this->parser->getRules();
		$this->parser->getRules();

		/** @var TestHandler $handler */
		$handler = $this->parser->getLogger()->getHandlers()[0];

		$treeCreateRecords = array_filter($handler->getRecords(), function(array $log) {
			return $log['message'] === 'Building directives tree...';
		});

		$this->assertCount(1, $treeCreateRecords);
	}
}
