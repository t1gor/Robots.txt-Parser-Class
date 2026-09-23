<?php declare(strict_types=1);

namespace Logger;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\Logger\BufferedLogger;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @covers \t1gor\RobotsTxtParser\Logger\BufferedLogger
 * @covers \t1gor\RobotsTxtParser\LogsIfAvailableTrait
 */
class BufferedLoggerTest extends TestCase {

	private function monolog(): array {
		$handler = new TestHandler(LogLevel::DEBUG);
		$logger  = new Logger(static::class);
		$logger->pushHandler($handler);

		return [$logger, $handler];
	}

	public function testItHoldsOnToMessagesUntilSomethingIsAttached() {
		$buffered = new BufferedLogger();
		$buffered->warning('held back');

		$this->assertCount(1, $buffered->getRecords());
		$this->assertSame('held back', $buffered->getRecords()[0]['message']);
	}

	public function testAttachingHandsEverythingOverInOrder() {
		[$logger, $handler] = $this->monolog();

		$buffered = new BufferedLogger();
		$buffered->debug('first');
		$buffered->warning('second');
		$buffered->attach($logger);

		$this->assertSame([], $buffered->getRecords(), 'the buffer is spent once handed over');
		$this->assertSame('first', $handler->getRecords()[0]['message']);
		$this->assertSame('second', $handler->getRecords()[1]['message']);
		$this->assertTrue($handler->hasRecordThatContains('second', Level::Warning));
	}

	public function testItForwardsRatherThanBuffersOnceAttached() {
		[$logger, $handler] = $this->monolog();

		$buffered = new BufferedLogger();
		$buffered->attach($logger);
		$buffered->debug('after the fact');

		$this->assertSame([], $buffered->getRecords());
		$this->assertTrue($handler->hasRecordThatContains('after the fact', Level::Debug));
	}

	public function testContextSurvivesTheHandover() {
		[$logger, $handler] = $this->monolog();

		$buffered = new BufferedLogger();
		$buffered->warning('limit {byte_limit}', ['byte_limit' => 1024]);
		$buffered->attach($logger);

		$this->assertSame(['byte_limit' => 1024], $handler->getRecords()[0]['context']);
	}

	/** An unattached logger must not become a memory leak of its own. */
	public function testTheBufferIsCappedAndDropsTheOldest() {
		$buffered = new BufferedLogger();

		foreach (range(1, BufferedLogger::MAX_RECORDS + 10) as $i) {
			$buffered->debug('message ' . $i);
		}

		$records = $buffered->getRecords();

		$this->assertCount(BufferedLogger::MAX_RECORDS, $records);
		$this->assertSame('message 11', $records[0]['message']);
		$this->assertSame('message ' . (BufferedLogger::MAX_RECORDS + 10), end($records)['message']);
	}

	public function testAttachingToItselfIsRefusedRatherThanLooping() {
		$buffered = new BufferedLogger();
		$buffered->debug('still here');
		$buffered->attach($buffered);

		$this->assertCount(1, $buffered->getRecords());
	}

	/**
	 * Stream filters are handed a logger when they are applied and never see a later one, so the
	 * buffered logger has to forward rather than merely replay.
	 */
	public function testFilterMessagesSurviveALoggerAttachedAfterConstruction() {
		[$logger, $handler] = $this->monolog();

		$parser = (new RobotsTxtParser())->setContent(fopen(__DIR__ . '/../Fixtures/with-commented-lines.txt', 'r'));
		$parser->setLogger($logger);
		$parser->getRules();

		$this->assertTrue(
			$handler->hasRecordThatContains('lines skipped as commented out', Level::Debug),
			stringifyLogs($handler->getRecords())
		);
	}

	/** Decided in the constructor, long before any logger could be handed over. */
	public function testConfigurationWarningsSurviveALateLogger() {
		[$logger, $handler] = $this->monolog();

		$parser = (new RobotsTxtParser(new Configuration(null)))->setContent(fopen(__DIR__ . '/../Fixtures/allow-spec.txt', 'r'));
		$parser->setLogger($logger);

		$this->assertTrue(
			$handler->hasRecordThatContains('exhaustion', Level::Warning),
			stringifyLogs($handler->getRecords())
		);
	}
}
