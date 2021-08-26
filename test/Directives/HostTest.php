<?php declare(strict_types=1);

namespace Directives;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\RobotsTxtParser;

class HostTest extends TestCase {

	protected ?RobotsTxtParser $parser;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->parser = new RobotsTxtParser(fopen(__DIR__ . '/../Fixtures/with-hosts.txt', 'r'));
		$this->parser->setLogger($log);
	}

	public function tearDown(): void {
		$this->parser = null;
	}

	public function testGetAllHosts() {
		$allHosts = $this->parser->getHost();
		$this->assertContains('myhost.ru', $allHosts);
		$this->assertContains('www.myhost.ru', $allHosts);
	}

	public function testHostForSomeUserAgent() {
		$yandexHost = $this->parser->getHost('Yandex');
		$this->assertEquals('www.myhost.ru', $yandexHost);
	}

	public function testHostForSomeUserAgentFallsBackToDefault() {
		$googleHost = $this->parser->getHost('Google');
		$this->assertEquals('myhost.ru', $googleHost);

		/** @var TestHandler $handler */
		$handler = $this->parser->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord('Failed for find host for Google, checking for * ...', LogLevel::DEBUG),
			json_encode($handler->getRecords())
		);
	}
}
