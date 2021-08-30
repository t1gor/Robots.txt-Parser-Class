<?php declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\WarmingMessages;
use function Utils\stringifyLogs;

class EncodingTest extends TestCase {

	private ?LoggerInterface $logger;

	protected function setUp(): void {
		$this->logger = new Logger(static::class);
		$this->logger->pushHandler(new TestHandler(LogLevel::DEBUG));
	}

	public function testLogsNonStandardEncoding() {
		$parser = new RobotsTxtParser(fopen(__DIR__ . '/Fixtures/market-yandex-Windows-1251.txt', 'r'), 'Windows-1251');
		$parser->setLogger($this->logger);
		$parser->getRules();

		/** @var TestHandler $handler */
		$handler = $parser->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord(WarmingMessages::ENCODING_NOT_UTF8, LogLevel::WARNING),
			stringifyLogs($handler->getRecords())
		);

		$this->assertTrue(
			$handler->hasRecord('Adding encoding filter convert.iconv.Windows-1251/utf-8', LogLevel::DEBUG),
			stringifyLogs($handler->getRecords())
		);
	}

	public function testWindows1251Readable() {
		$parser = new RobotsTxtParser(fopen(__DIR__ . '/Fixtures/market-yandex-Windows-1251.txt', 'r'), 'Windows-1251');
		$parser->setLogger($this->logger);

		$allRules = $parser->getRules();
		$this->assertCount(5, $allRules, json_encode(array_keys($allRules)));
	}

	public function testShouldNotChangeInternalEncoding() {
		$this->assertEquals('UTF-8', mb_internal_encoding());
		$parser = new RobotsTxtParser('', 'iso-8859-1');
		$this->assertEquals('UTF-8', mb_internal_encoding());
	}
}
