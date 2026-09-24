<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * Input that is valid robots.txt but carries bytes the parser mishandles.
 *
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::getRules
 */
class MalformedInputTest extends TestCase {

	const BOM  = "\xEF\xBB\xBF";
	const NBSP = "\xC2\xA0";

	/**
	 * A BOM keeps the first directive from matching, so rules land under "*" instead.
	 *
	 * @group known-issues
	 */
	public function testBomBeforeFirstDirective() {
		$parser = (new RobotsTxtParser())->setContent(self::BOM . "User-agent: googlebot\nDisallow: /admin\n");

		$this->assertSame(['googlebot' => ['disallow' => ['/admin']]], $parser->getRules());
	}

	public function testWithoutBomTheAgentIsScopedCorrectly() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: googlebot\nDisallow: /admin\n");

		$this->assertSame(['googlebot' => ['disallow' => ['/admin']]], $parser->getRules());
	}

	/** One bad byte must not blank the whole bucket. */
	public function testInvalidUtf8ByteDoesNotDiscardTheWholeFile() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: googlebot\nDisallow: /a\nDisallow: /caf\xE9\nDisallow: /b\n");

		$this->assertTrue($parser->isDisallowed('/a', 'googlebot'), 'rule before the bad byte');
		$this->assertTrue($parser->isDisallowed('/b', 'googlebot'), 'rule after the bad byte');
	}

	/**
	 * \s is ASCII-only without PCRE_UCP, so a non-breaking space is not consumed.
	 *
	 * @group known-issues
	 */
	public function testNonBreakingSpaceAfterColon() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow:" . self::NBSP . "/admin\n");

		$this->assertTrue($parser->isDisallowed('/admin'));
	}

	public function testRegularSpaceAfterColon() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\n");

		$this->assertTrue($parser->isDisallowed('/admin'));
	}
}
