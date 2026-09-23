<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * Rule paths are literals apart from `*` and a trailing `$`; regex metachars in them
 * used to reach preg_match() unescaped.
 *
 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/59
 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/87
 *
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::prepareRegexRule
 */
class RegexMetaCharsTest extends TestCase {

	public function metaCharProvider(): array {
		// rule, path that must match, path that must not
		return [
			'parenthesis open'  => ['/path(foo', '/path(foo/bar', '/path'],
			'parenthesis close' => ['/d)e', '/d)e', '/de'],
			'plus'              => ['/u+v', '/u+v', '/uv'],
			'dot'               => ['/a.b', '/a.b', '/axb'],
			'square brackets'   => ['/b[c]', '/b[c]', '/bc'],
			'dollar mid-rule'   => ['/pri$ce', '/pri$ce', '/price'],
			'question mark'     => ['/q?r', '/q?r', '/qXr'],
		];
	}

	/**
	 * @dataProvider metaCharProvider
	 */
	public function testMetaCharsMatchLiterally(string $rule, string $matches, string $doesNotMatch) {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: {$rule}\n");

		$this->assertTrue($parser->isDisallowed($matches), "{$rule} should match {$matches}");
		$this->assertFalse($parser->isDisallowed($doesNotMatch), "{$rule} should not match {$doesNotMatch}");
	}

	/**
	 * `*` + `+` used to translate to `.*+`, a possessive quantifier that swallowed anything.
	 *
	 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/87
	 */
	public function testWildcardNextToPlusIsNotAPossessiveQuantifier() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /blogs/*+*\nDisallow: /collections/*+*\n");

		$this->assertFalse($parser->isDisallowed('/collections/belts'));
		$this->assertFalse($parser->isDisallowed('/blogs/plain'));
		$this->assertTrue($parser->isDisallowed('/collections/a+b'));
		$this->assertTrue($parser->isDisallowed('/blogs/x+y'));
	}

	/**
	 * Unbalanced metachars used to emit "preg_match(): Compilation failed".
	 *
	 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/59
	 */
	public function testNoWarningsOnUnbalancedMetaChars() {
		$raised = [];
		set_error_handler(function (int $no, string $str) use (&$raised) {
			$raised[] = $str;
			return true;
		});

		try {
			foreach (['/path(foo', '/d)e', '/a{2', '/x|y', '/p^q', '/s\\t', '/b[c'] as $rule) {
				$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: {$rule}\n");
				$parser->isDisallowed('/some/path');
			}
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised, 'no PHP warnings expected');
	}

	/** `*` is still a wildcard and a trailing `$` still anchors. */
	public function testWildcardAndEndAnchorStillWork() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /a/*/b\nDisallow: /exact$\n");

		$this->assertTrue($parser->isDisallowed('/a/anything/b'));
		$this->assertFalse($parser->isDisallowed('/a/b'));
		$this->assertTrue($parser->isDisallowed('/exact'));
		$this->assertFalse($parser->isDisallowed('/exact/more'));
	}

	/**
	 * Both sides are percent-encoded before comparison.
	 *
	 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/69
	 */
	public function testPercentEncodedPathStillMatchesRawRule() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /x|y\n");

		$this->assertTrue($parser->isDisallowed('/x|y'));
	}
}
