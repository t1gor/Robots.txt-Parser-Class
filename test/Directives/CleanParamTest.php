<?php declare(strict_types=1);

namespace Directives;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::getCleanParam
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::checkRuleSwitch
 */
class CleanParamTest extends TestCase
{
	protected ?RobotsTxtParser $parser;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->parser = (new RobotsTxtParser())->setContent(fopen(__DIR__ . '/../Fixtures/with-clean-param.txt', 'r'));
		$this->parser->setLogger($log);
	}

	public function tearDown(): void {
		$this->parser = null;
	}

	public function testReturnsAnEmptyArrayAndLogsWhenTheDirectiveIsAbsent() {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$parser = (new RobotsTxtParser())->setContent("User-Agent: *\nDisallow: /admin\n");
		$parser->setLogger($log);

		// this used to warn on an undefined key and then fail the array return type outright
		$this->assertSame([], $parser->getCleanParam());

		/** @var TestHandler $handler */
		$handler = $parser->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord('clean-param directive: Not found', Level::Debug),
			stringifyLogs($handler->getRecords())
		);
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
		$this->markTestIncomplete('@TODO this needs to be finished yet.');

		$this->assertTrue($this->parser->isDisallowed("http://www.site1.com/forums/showthread.php?s=681498b9648949605&ref=parent"));
		$this->assertFalse($this->parser->isAllowed("http://www.site1.com/forums/showthread.php?s=681498b9648949605&ref=parent"));

		/** @var TestHandler $handler */
		$handler = $this->parser->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord('Rule match: clean-param directive', Level::Debug),
			stringifyLogs($handler->getRecords())
		);

		$this->assertTrue($this->parser->isAllowed("http://www.site2.com/forums/showthread.php?s=681498b9648949605"));
		$this->assertFalse($this->parser->isDisallowed("http://www.site2.com/forums/showthread.php?s=681498b9648949605"));

		$this->assertTrue(
			$handler->hasRecord('Rule match: Path', Level::Debug),
			stringifyLogs($handler->getRecords())
		);
	}

	/**
	 * Inlined clean-param/host used to hit dead switch arms that called a removed method
	 * and fell through without returning.
	 *
	 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/127
	 */
	public function testInlinedDirectivesAreTreatedAsPlainRules() {
		$check = new \ReflectionMethod(RobotsTxtParser::class, 'checkRuleSwitch');
		$check->setAccessible(true);

		$this->assertFalse($check->invoke($this->parser, 'clean-param: ref /forum/showthread.php', '/forum/showthread.php'));
		$this->assertFalse($check->invoke($this->parser, 'host: example.com', '/forum/showthread.php'));
		$this->assertTrue($check->invoke($this->parser, '/forum/', '/forum/showthread.php'));
	}
}
