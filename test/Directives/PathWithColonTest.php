<?php declare(strict_types=1);

namespace Directives;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * A path may hold colons, so Allow/Disallow keep everything past the directive name - as Sitemap
 * and Host always did.
 *
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\AbstractAllowanceProcessor::process
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\AbstractDirectiveProcessor::value
 */
class PathWithColonTest extends TestCase {

	public function testPathWithColonsIsStoredWhole() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /path:with:colon\n");

		$this->assertSame(['/path:with:colon'], $parser->getRules()['*']['disallow']);
	}

	/** Truncation widened the rule, so unrelated paths got blocked too. */
	public function testTruncatedPathDoesNotOverBlock() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /path:with:colon\n");

		$this->assertFalse($parser->isDisallowed('/pathOTHER'));
	}

	public function testQueryStringContainingUrlIsStoredWhole() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /out?url=https://example.com/\n");

		$this->assertSame(['/out?url=https://example.com/'], $parser->getRules()['*']['disallow']);
	}

	/** A run of colons is one separator, as the filters read it. */
	public function testDoubledColonIsHonoured() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow::/x\n");

		$this->assertTrue($parser->isDisallowed('/x'));
	}

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
