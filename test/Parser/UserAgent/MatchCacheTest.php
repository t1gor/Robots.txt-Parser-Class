<?php declare(strict_types=1);

namespace Parser\UserAgent;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use t1gor\RobotsTxtParser\Parser\UserAgent\UserAgentMatcher;
use t1gor\RobotsTxtParser\Parser\UserAgent\UserAgentMatcherInterface;
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * Matching walks every user-agent in the tree, so the parser keeps the answer. What matters is
 * that it is dropped the moment the tree it was true for is gone.
 */
class MatchCacheTest extends TestCase {

	private function parser(string $content, CountingMatcher $matcher): RobotsTxtParser {
		return (new RobotsTxtParser(new Configuration(), null, null, $matcher))->setContent($content);
	}

	public function testRepeatedQueriesMatchOnce() {
		$matcher = new CountingMatcher();
		$parser  = $this->parser("User-agent: Googlebot\nDisallow: /admin\n", $matcher);

		for ($i = 0; $i < 20; $i++) {
			$this->assertTrue($parser->isDisallowed('/admin', 'Googlebot'));
		}

		$this->assertSame(1, $matcher->calls, 'the match should be resolved once, then reused');
	}

	public function testANewDocumentIsMatchedAgain() {
		$matcher = new CountingMatcher();
		$parser  = $this->parser("User-agent: Googlebot\nDisallow: /admin\n", $matcher);

		$this->assertTrue($parser->isDisallowed('/admin', 'Googlebot'));

		// the second document has no rules for it at all, so a stale match would answer wrongly
		$parser->setContent("User-agent: Bingbot\nDisallow: /admin\n");

		$this->assertFalse($parser->isDisallowed('/admin', 'Googlebot'));
		$this->assertSame(2, $matcher->calls);
	}

	public function testTheCacheStaysBounded() {
		$matcher = new CountingMatcher();
		$parser  = $this->parser("User-agent: Googlebot\nDisallow: /admin\n", $matcher);

		for ($i = 0; $i < 600; $i++) {
			$parser->isAllowed('/admin', 'Crawler' . $i . 'bot');
		}

		$cached = (new \ReflectionProperty(RobotsTxtParser::class, 'matched'))->getValue($parser);

		$this->assertLessThanOrEqual(512, count($cached), 'an unbounded cache is a leak');
	}
}

class CountingMatcher implements UserAgentMatcherInterface {

	public int $calls = 0;

	private UserAgentMatcher $inner;

	public function __construct(?LoggerInterface $logger = null) {
		$this->inner = new UserAgentMatcher($logger);
	}

	public function setLogger(LoggerInterface $logger): void {
		$this->inner->setLogger($logger);
	}

	public function getMatching(string $userAgent, array $available = []): string {
		$this->calls++;

		return $this->inner->getMatching($userAgent, $available);
	}
}
