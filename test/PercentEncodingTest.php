<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * RFC 9309: octets outside ASCII and those in the reserved range MUST be percent-encoded
 * on both the URI and the robots.txt path before comparison. Paths were encoded, rules were not.
 *
 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/69
 * @link https://www.rfc-editor.org/rfc/rfc9309#section-2.2.2
 *
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::prepareRegexRule
 * @covers \t1gor\RobotsTxtParser\Parser\Url::encode
 */
class PercentEncodingTest extends TestCase {

	public function pathProvider(): array {
		// rule, path that must match, path that must not
		return [
			'non-ascii latin' => ['/café', '/café', '/cafe'],
			'cyrillic'        => ['/каталог', '/каталог', '/katalog'],
			'pipe'            => ['/x|y', '/x|y', '/xy'],
			'caret'           => ['/p^q', '/p^q', '/pq'],
			'curly brace'     => ['/a{2}', '/a{2}', '/a2'],
			'backslash'       => ['/s\\t', '/s\\t', '/st'],
			'space'           => ['/two words', '/two words', '/twowords'],
		];
	}

	/**
	 * @dataProvider pathProvider
	 */
	public function testRuleMatchesItsOwnPath(string $rule, string $matches, string $doesNotMatch) {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: {$rule}\n");

		$this->assertTrue($parser->isDisallowed($matches), "{$rule} should match {$matches}");
		$this->assertFalse($parser->isDisallowed($doesNotMatch), "{$rule} should not match {$doesNotMatch}");
	}

	/** An already-encoded path matches the readable rule, and vice versa. */
	public function testEncodedAndDecodedFormsAreEquivalent() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /café\n");

		$this->assertTrue($parser->isDisallowed('/café'));
		$this->assertTrue($parser->isDisallowed('/caf%C3%A9'));
	}

	/** Encoding happens at match time, so the tree stays readable. */
	public function testRulesAreStoredUnencoded() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /café\nDisallow: /x|y\n");

		$this->assertSame(['/café', '/x|y'], $parser->getRules()['*']['disallow']);
	}

	/** Wildcards and anchors still work on encoded rules. */
	public function testWildcardAndAnchorWithNonAscii() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /café/*/photo\nDisallow: /menü$\n");

		$this->assertTrue($parser->isDisallowed('/café/2024/photo'));
		$this->assertFalse($parser->isDisallowed('/café/photo'));
		$this->assertTrue($parser->isDisallowed('/menü'));
		$this->assertFalse($parser->isDisallowed('/menü/extra'));
	}
}
