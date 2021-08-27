<?php declare(strict_types=1);

namespace Directives;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @TODO finish this
 */
class CleanParamTest extends TestCase
{
	protected ?RobotsTxtParser $parser;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->parser = new RobotsTxtParser(fopen(__DIR__ . '/../Fixtures/with-clean-param.txt', 'r'));
		$this->parser->setLogger($log);
	}

	public function tearDown(): void {
		$this->parser = null;
	}

	public function testCleanParam() {
		$this->assertArrayHasKey('/forum/showthread.php', $this->parser->getCleanParam());
		$this->assertEquals(['abc'], $this->parser->getCleanParam()['/forum/showthread.php']);

		$this->assertArrayHasKey('/forum/*.php', $this->parser->getCleanParam());
		$this->assertEquals(['sid', 'sort'], $this->parser->getCleanParam()['/forum/*.php']);

		$this->assertArrayHasKey('/*', $this->parser->getCleanParam());
		$this->assertEquals(['someTrash', 'otherTrash'], $this->parser->getCleanParam()['/*']);
	}

	public function testCleanParamsAppliedForAllowDisallow() {
		$this->markTestSkipped('@TODO');

		$this->assertTrue($this->parser->isDisallowed("http://www.site1.com/forums/showthread.php?s=681498b9648949605&ref=parent"));
		$this->assertFalse($this->parser->isAllowed("http://www.site1.com/forums/showthread.php?s=681498b9648949605&ref=parent"));

		/** @var TestHandler $handler */
		$handler = $this->parser->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord('Rule match: clean-param directive', LogLevel::DEBUG),
			json_encode($handler->getRecords())
		);

		$this->assertTrue($this->parser->isAllowed("http://www.site2.com/forums/showthread.php?s=681498b9648949605"));
		$this->assertFalse($this->parser->isDisallowed("http://www.site2.com/forums/showthread.php?s=681498b9648949605"));

		$this->assertTrue(
			$handler->hasRecord('Rule match: Path', LogLevel::DEBUG),
			json_encode($handler->getRecords())
		);
	}
}
