<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

class AllowTest extends TestCase {

	private ?RobotsTxtParser $parser;

	public function setUp(): void {
		$this->parser = new RobotsTxtParser(fopen(__DIR__ . '/Fixtures/allow-spec.txt', 'r'));
	}

	public function tearDown(): void {
		$this->parser = null;
	}

	public function testForCrawlerZ() {
		$this->assertTrue($this->parser->isAllowed('/', 'crawlerZ'));
		$this->assertTrue($this->parser->isDisallowed('/forum', 'crawlerZ'));
		$this->assertTrue($this->parser->isDisallowed('/public', 'crawlerZ'));
		$this->assertFalse($this->parser->isDisallowed('/', 'crawlerZ'));
		$this->assertFalse($this->parser->isAllowed('/forum', 'crawlerZ'));
		$this->assertFalse($this->parser->isAllowed('/public', 'crawlerZ'));
	}

	public function testForDefaultUserAgent() {
		$this->assertTrue($this->parser->isAllowed('/'));
		$this->assertTrue($this->parser->isAllowed('/article'));
		$this->assertTrue($this->parser->isDisallowed('/temp'));
		$this->assertTrue($this->parser->isDisallowed('/Admin'));
		$this->assertTrue($this->parser->isDisallowed('/admin'));
		$this->assertTrue($this->parser->isDisallowed('/admin/cp/test/'));
		$this->assertFalse($this->parser->isDisallowed('/'));
		$this->assertFalse($this->parser->isDisallowed('/article'));
		$this->assertFalse($this->parser->isAllowed('/temp'));
		$this->assertFalse($this->parser->isDisallowed('/article'));
	}

	public function testForAgentV() {
		$this->assertTrue($this->parser->isDisallowed('/foo', 'agentV'));
		$this->assertTrue($this->parser->isAllowed('/bar', 'agentV'));
		$this->assertTrue($this->parser->isAllowed('/Foo', 'agentV'));
	}

	public function testForAgentW() {
		$this->assertTrue($this->parser->isDisallowed('/foo', 'agentW'));
		$this->assertTrue($this->parser->isAllowed('/bar', 'agentW'));
		$this->assertTrue($this->parser->isAllowed('/Foo', 'agentW'));
	}

	public function testForBotY() {
		$this->assertTrue($this->parser->isDisallowed('/', 'botY-test'));
		$this->assertTrue($this->parser->isDisallowed('/forum', 'botY-test'));
		$this->assertTrue($this->parser->isAllowed('/forum/', 'botY-test'));
		$this->assertTrue($this->parser->isDisallowed('/forum/topic', 'botY-test'));
		$this->assertTrue($this->parser->isDisallowed('/public', 'botY-test'));
		$this->assertFalse($this->parser->isAllowed('/', 'botY-test'));
		$this->assertFalse($this->parser->isAllowed('/forum', 'botY-test'));
		$this->assertFalse($this->parser->isDisallowed('/forum/', 'botY-test'));
		$this->assertFalse($this->parser->isAllowed('/forum/topic', 'botY-test'));
		$this->assertFalse($this->parser->isAllowed('/public', 'botY-test'));
	}

	/**
	 * @param string $url
	 * @param bool   $isAllowed
	 *
	 * @dataProvider generateDataForSpiderX
	 */
	public function testForSpiderX(string $url, bool $isAllowed) {
		if ($isAllowed) {
			$this->assertTrue($this->parser->isAllowed($url, 'spiderX/1.0'));
			$this->assertFalse($this->parser->isDisallowed($url, 'spiderX/1.0'));
		} else {
			$this->assertTrue($this->parser->isDisallowed($url, 'spiderX/1.0'));
			$this->assertFalse($this->parser->isAllowed($url, 'spiderX/1.0'));
		}
	}

	public function generateDataForSpiderX(): array {
		return [
			['/temp', true],
			['/assets', false],
			['/forum', true],
		];
	}
}
