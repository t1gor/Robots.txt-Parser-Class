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
			'crawl-delay',
			'disallow',
			'host',
			'request-rate',
			'sitemap',
			'user-agent',
			'visit-time',
		];

		$actual = Directive::getAll();
		sort($actual);

		$this->assertSame($expected, $actual);
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
}
