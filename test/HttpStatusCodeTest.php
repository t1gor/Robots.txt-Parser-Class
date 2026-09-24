<?php declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\RobotsTxtParser;

class HttpStatusCodeTest extends TestCase {

	private ?RobotsTxtParser $parser;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->parser = (new RobotsTxtParser())->setContent(fopen(__DIR__ . '/Fixtures/allow-all.txt', 'r'));
		$this->parser->setLogger($log);
	}

	public function tearDown(): void {
		$this->parser = null;
	}

	public function testHttpStatusCodeValid() {
		$this->parser->setHttpStatusCode(200);
		$this->assertTrue($this->parser->isAllowed("/"));
		$this->assertFalse($this->parser->isDisallowed("/"));

		/** @var TestHandler $handler */
		$handler = $this->parser->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord("Rule match: Path", Level::Debug),
			stringifyLogs($handler->getRecords())
		);
	}

	public function testHttpStatusCodeInvalid() {
		$this->parser->setHttpStatusCode(503);
		$this->assertTrue($this->parser->isDisallowed("/"));
		$this->assertFalse($this->parser->isAllowed("/"));

		/** @var TestHandler $handler */
		$handler = $this->parser->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord("Disallowed by HTTP status code 503", Level::Debug),
			stringifyLogs($handler->getRecords())
		);
	}
}
