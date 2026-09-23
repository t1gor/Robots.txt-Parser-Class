<?php declare(strict_types=1);

namespace Stream\Filter;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @covers \t1gor\RobotsTxtParser\Stream\Filters\EnsureEndOfLinesFilter
 */
class EnsureEndOfLinesFilterTest extends TestCase {

	public function eolProvider(): array {
		return [
			'unix'    => ["\n"],
			'windows' => ["\r\n"],
		];
	}

	/**
	 * @dataProvider eolProvider
	 */
	public function testParsesRegardlessOfLineEnding(string $eol) {
		$parser = new RobotsTxtParser(
			implode($eol, ['User-agent: *', 'Disallow: /tech', 'Allow: /tech/public', ''])
		);

		// a stray CR would end up inside the rule value
		$this->assertSame(['*' => [
			'disallow' => ['/tech'],
			'allow'    => ['/tech/public'],
		]], $parser->getRules());

		$this->assertTrue($parser->isDisallowed('/tech/secret'));
		$this->assertTrue($parser->isAllowed('/tech/public'));
	}
}
