<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * checkBasicRule() keeps the pattern it compiled for each rule. The cap only matters for a
 * document with more distinct rules than any real one carries, so it is lowered here rather than
 * building a hundred thousand of them.
 *
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::checkBasicRule
 */
class PatternCacheTest extends TestCase {

	private const ROBOTS = "User-agent: *\nDisallow: /alpha\nDisallow: /beta\nDisallow: /gamma\nDisallow: /delta\nAllow: /alpha/public\n";

	private function parser(): RobotsTxtParser {
		return (new RobotsTxtParser())->setContent(self::ROBOTS);
	}

	/** A cache is only correct if it is invisible, so the answers must not depend on it. */
	public function testAnswersDoNotChangeWhenThePatternCacheEvicts() {
		$tiny = new class extends RobotsTxtParser {
			protected const MAX_PATTERNS = 2;
		};

		$tiny->setContent(self::ROBOTS);
		$plain = $this->parser();

		$paths = ['/alpha', '/alpha/public', '/beta/x', '/gamma', '/delta/y', '/unlisted'];

		// more distinct rules than the cap, so it clears part-way through every lookup
		foreach ($paths as $path) {
			$this->assertSame(
				$plain->isAllowed($path),
				$tiny->isAllowed($path),
				"eviction changed the answer for {$path}"
			);
		}
	}

	public function testRepeatedLookupsAgainstTheSamePathStayStable() {
		$parser = $this->parser();

		$first = $parser->isAllowed('/alpha/public');

		for ($i = 0; $i < 5; $i++) {
			$this->assertSame($first, $parser->isAllowed('/alpha/public'), 'cached pattern drifted');
		}

		$this->assertTrue($first, '/alpha/public is allowed by the longer Allow rule');
		$this->assertFalse($parser->isAllowed('/alpha'), '/alpha is disallowed');
	}

	/** A pattern is only valid for the rule it was built from, so a new document must not reuse it. */
	public function testCacheDoesNotLeakAcrossDocuments() {
		$parser = $this->parser();

		$this->assertFalse($parser->isAllowed('/beta/x'), '/beta is disallowed by the first document');

		$parser->setContent("User-agent: *\nAllow: /beta\nDisallow: /omega\n");

		$this->assertTrue($parser->isAllowed('/beta/x'), '/beta is allowed by the second document');
		$this->assertFalse($parser->isAllowed('/omega'), '/omega is disallowed by the second document');
	}
}
