<?php declare(strict_types=1);

namespace Directives;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * Allow/Disallow take explode(':', $line)[1], so anything after a second colon is lost.
 * Sitemap and Host re-join the parts and are unaffected.
 *
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\AbstractAllowanceProcessor::process
 */
class PathWithColonTest extends TestCase {

	/**
	 * Stored as "/path".
	 *
	 * @group known-issues
	 */
	public function testPathWithColonsIsStoredWhole() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /path:with:colon\n");

		$this->assertSame(['/path:with:colon'], $parser->getRules()['*']['disallow']);
	}

	/**
	 * Truncation widens the rule, so unrelated paths get blocked too.
	 *
	 * @group known-issues
	 */
	public function testTruncatedPathDoesNotOverBlock() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /path:with:colon\n");

		$this->assertFalse($parser->isDisallowed('/pathOTHER'));
	}

	/**
	 * Stored as "/out?url=https".
	 *
	 * @group known-issues
	 */
	public function testQueryStringContainingUrlIsStoredWhole() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /out?url=https://example.com/\n");

		$this->assertSame(['/out?url=https://example.com/'], $parser->getRules()['*']['disallow']);
	}

	/**
	 * The value is empty, so the rule is dropped.
	 *
	 * @group known-issues
	 */
	public function testDoubledColonIsHonoured() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow::/x\n");

		$this->assertTrue($parser->isDisallowed('/x'));
	}

	/** Sitemap already handles colons correctly - the pattern Allow/Disallow should copy. */
	public function testSitemapWithColonsIsStoredWhole() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nSitemap: https://example.com:8443/s.xml\n");

		$this->assertSame(['https://example.com:8443/s.xml'], $parser->getSitemaps());
	}

	/** Host validates as a bare hostname, so a scheme-prefixed value is dropped. */
	public function testSchemePrefixedHostIsRejected() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nHost: https://example.com\n");

		$this->assertNull($parser->getHost('*'));
	}
}
