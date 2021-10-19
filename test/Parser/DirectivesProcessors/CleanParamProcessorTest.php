<?php declare(strict_types=1);

namespace Parser\DirectivesProcessors;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CleanParamProcessor;

/**
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CleanParamProcessor::process
 */
class CleanParamProcessorTest extends TestCase {

	private ?CleanParamProcessor $processor;

	public function setUp(): void {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$this->processor = new CleanParamProcessor($log);
	}

	public function tearDown(): void {
		$this->processor = null;
	}

	public function testProcessesCorrectlyWithPath() {
		$tree = [];
		$line = 'Clean-param: some&someMore /only/here';

		$this->processor->process($line, $tree);

		$this->assertArrayHasKey(Directive::CLEAN_PARAM, $tree);
		$this->assertArrayHasKey('/only/here', $tree[Directive::CLEAN_PARAM], json_encode($tree[Directive::CLEAN_PARAM]));
		$this->assertContains('some', $tree[Directive::CLEAN_PARAM]['/only/here'], json_encode($tree[Directive::CLEAN_PARAM]));
		$this->assertContains('someMore', $tree[Directive::CLEAN_PARAM]['/only/here'], json_encode($tree[Directive::CLEAN_PARAM]));
	}

	public function testProcessesCorrectlyWithNoPath() {
		$tree = [];
		$line = 'Clean-param: some&someMore';

		$this->processor->process($line, $tree);

		$this->assertArrayHasKey(Directive::CLEAN_PARAM, $tree);
		$this->assertArrayHasKey('/*', $tree[Directive::CLEAN_PARAM], json_encode($tree[Directive::CLEAN_PARAM]));
		$this->assertContains('some', $tree[Directive::CLEAN_PARAM]['/*'], json_encode($tree[Directive::CLEAN_PARAM]));
		$this->assertContains('someMore', $tree[Directive::CLEAN_PARAM]['/*'], json_encode($tree[Directive::CLEAN_PARAM]));
	}
}
