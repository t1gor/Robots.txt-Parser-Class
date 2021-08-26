<?php declare(strict_types=1);

namespace Parser\DirectivesProcessors;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CleanParamProcessor;

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
		$func = $this->processor;
		$line = 'Clean-param: some&someMore /only/here';

		$func($line, $tree);

		$this->assertArrayHasKey(Directive::CLEAN_PARAM, $tree);
		$this->assertArrayHasKey('/only/here', $tree[Directive::CLEAN_PARAM], json_encode($tree[Directive::CLEAN_PARAM]));
		$this->assertContains('some', $tree[Directive::CLEAN_PARAM]['/only/here'], json_encode($tree[Directive::CLEAN_PARAM]));
		$this->assertContains('someMore', $tree[Directive::CLEAN_PARAM]['/only/here'], json_encode($tree[Directive::CLEAN_PARAM]));
	}

	public function testProcessesCorrectlyWithNoPath() {
		$tree = [];
		$func = $this->processor;
		$line = 'Clean-param: some&someMore';

		$func($line, $tree);

		$this->assertArrayHasKey(Directive::CLEAN_PARAM, $tree);
		$this->assertArrayHasKey('/*', $tree[Directive::CLEAN_PARAM], json_encode($tree[Directive::CLEAN_PARAM]));
		$this->assertContains('some', $tree[Directive::CLEAN_PARAM]['/*'], json_encode($tree[Directive::CLEAN_PARAM]));
		$this->assertContains('someMore', $tree[Directive::CLEAN_PARAM]['/*'], json_encode($tree[Directive::CLEAN_PARAM]));
	}
}
