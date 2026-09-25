<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Directive;

/**
 * @covers \t1gor\RobotsTxtParser\Directive
 */
class DirectiveTest extends TestCase {

	/**
	 * The one thing cases() must not be simplified into: CACHE is an argument alias for
	 * getDelay(), never a name a file carries, and listing it here would make
	 * SkipUnsupportedDirectivesFilter start keeping "Cache:" lines.
	 */
	public function testGetAllOmitsTheCacheAlias() {
		$this->assertNotContains(Directive::CACHE->value, Directive::getAll());
		$this->assertContains(Directive::CACHE_DELAY->value, Directive::getAll());
		$this->assertCount(count(Directive::cases()) - 1, Directive::getAll());
	}

	/**
	 * How the writers spell a directive out. Every case is ASCII, so ucfirst() is enough - a
	 * hyphenated name keeps its lower-case second word, which is what robots.txt uses.
	 *
	 * @dataProvider provideLabels
	 */
	public function testLabelIsTheWrittenSpelling(Directive $directive, string $expected) {
		$this->assertSame($expected, $directive->label());
	}

	public function provideLabels(): array {
		return [
			[Directive::USERAGENT, 'User-agent'],
			[Directive::DISALLOW, 'Disallow'],
			[Directive::ALLOW, 'Allow'],
			[Directive::HOST, 'Host'],
			[Directive::SITEMAP, 'Sitemap'],
			[Directive::CLEAN_PARAM, 'Clean-param'],
			[Directive::CRAWL_DELAY, 'Crawl-delay'],
			[Directive::CACHE_DELAY, 'Cache-delay'],
			[Directive::REQUEST_RATE, 'Request-rate'],
			[Directive::VISIT_TIME, 'Visit-time'],
			[Directive::ROBOT_VERSION, 'Robot-version'],
			[Directive::COMMENT, 'Comment'],
			[Directive::NOINDEX, 'Noindex'],
		];
	}

	public function testGetAllReturnsPlainStrings() {
		foreach (Directive::getAll() as $directive) {
			$this->assertIsString($directive);
		}
	}

	/** Pinned so a reordering or rename cannot silently change what the filters match. */
	public function testGetAllListsEveryParsableDirective() {
		$expected = [
			'allow',
			'cache-delay',
			'clean-param',
			'comment',
			'crawl-delay',
			'disallow',
			'host',
			'noindex',
			'request-rate',
			'robot-version',
			'sitemap',
			'user-agent',
			'visit-time',
		];

		$actual = Directive::getAll();
		sort($actual);

		$this->assertSame($expected, $actual);
	}

	/** What a group may carry several of - the rest keep the last value seen. */
	public function testRepeatableDirectives() {
		$this->assertTrue(Directive::DISALLOW->isRepeatable());
		$this->assertTrue(Directive::REQUEST_RATE->isRepeatable());
		$this->assertTrue(Directive::COMMENT->isRepeatable());
		$this->assertFalse(Directive::VISIT_TIME->isRepeatable());
		$this->assertFalse(Directive::ROBOT_VERSION->isRepeatable());
		$this->assertFalse(Directive::CRAWL_DELAY->isRepeatable());
	}

	/** The lookahead used to be dodged by backtracking, which dropped every Request-rate line. */
	public function testRequestRateRegexOnlyMatchesInvalidValues() {
		$this->assertSame(0, preg_match(Directive::getRequestRateRegex(), 'Request-rate: 1/5m 0600-0845'));
		$this->assertSame(0, preg_match(Directive::getRequestRateRegex(), 'Request-rate:1/5'));
		$this->assertSame(1, preg_match(Directive::getRequestRateRegex(), 'Request-rate: 15686'));
		$this->assertSame(1, preg_match(Directive::getRequestRateRegex(), 'Request-rate: ngdndganda'));
	}

	public function testUnsupportedDirectiveRegexStillSkipsCacheLines() {
		$this->assertSame(1, preg_match(Directive::getRegex(), 'Cache: 5'));
		$this->assertSame(0, preg_match(Directive::getRegex(), 'Cache-Delay: 5'));
		$this->assertSame(0, preg_match(Directive::getRegex(), 'Disallow: /admin'));
	}

	public function testAttemptGetInlineIsUnaffectedByDeclarationOrder() {
		$this->assertSame('cache-delay', Directive::attemptGetInline('Cache-Delay: 5'));
		$this->assertSame('crawl-delay', Directive::attemptGetInline('Crawl-Delay: 5'));
		$this->assertFalse(Directive::attemptGetInline('Cache: 5'));
		$this->assertFalse(Directive::attemptGetInline('Nonsense: 5'));
	}

	public function testAttemptGetInlineIsCaseInsensitive() {
		$this->assertSame('disallow', Directive::attemptGetInline('DISALLOW: /admin'));
		$this->assertSame('user-agent', Directive::attemptGetInline('User-Agent: *'));
	}

	public function testStripInlineRemovesTheDirectiveAndSurroundingSpace() {
		$this->assertSame('/admin', Directive::stripInline('Disallow: /admin'));
		$this->assertSame('/admin', Directive::stripInline('disallow:/admin'));
		$this->assertSame('*', Directive::stripInline('User-Agent:   *  '));
	}

	public function testStripInlineLeavesAnUnrecognisedLineAlone() {
		$this->assertSame('Nonsense: /admin', Directive::stripInline('Nonsense: /admin'));
		$this->assertSame('', Directive::stripInline(''));
	}
}
