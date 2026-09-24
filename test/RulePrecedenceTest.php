<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * Allow/Disallow are not applied in file order - the most specific (longest) matching rule wins,
 * and allow wins a tie.
 *
 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/76
 * @see https://www.rfc-editor.org/rfc/rfc9309#section-2.2.2
 *
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::checkRules
 */
class RulePrecedenceTest extends TestCase {

	/**
	 * The example from the Yandex docs quoted in the issue.
	 */
	public function testYandexSortingExample() {
		$parser = (new RobotsTxtParser())->setContent(implode("\n", [
			'User-agent: *',
			'Allow: /',
			'Allow: /catalog/auto',
			'Disallow: /catalog',
		]));

		$this->assertTrue($parser->isDisallowed('/catalog/'));
		$this->assertFalse($parser->isAllowed('/catalog/'));

		$this->assertTrue($parser->isAllowed('/catalog/auto'));
		$this->assertTrue($parser->isAllowed('/'));
		$this->assertTrue($parser->isAllowed('/news'));

		// the issue reports it with an absolute URL
		$this->assertTrue($parser->isDisallowed('http://test.ru/catalog/'));
		$this->assertTrue($parser->isAllowed('http://test.ru/catalog/auto'));
	}

	/**
	 * Same rules, every possible order - the verdict must not move.
	 */
	public function testOrderOfDirectivesDoesNotMatter() {
		$rules = ['Allow: /', 'Allow: /catalog/auto', 'Disallow: /catalog'];

		foreach ([[0, 1, 2], [0, 2, 1], [1, 0, 2], [1, 2, 0], [2, 0, 1], [2, 1, 0]] as $order) {
			$body   = array_map(function (int $idx) use ($rules): string { return $rules[$idx]; }, $order);
			$parser = (new RobotsTxtParser())->setContent("User-agent: *\n" . implode("\n", $body) . "\n");
			$as     = implode(', ', $body);

			$this->assertTrue($parser->isDisallowed('/catalog/'), "/catalog/ with {$as}");
			$this->assertTrue($parser->isAllowed('/catalog/auto'), "/catalog/auto with {$as}");
			$this->assertTrue($parser->isAllowed('/'), "/ with {$as}");
		}
	}

	/**
	 * @dataProvider precedenceProvider
	 */
	public function testMostSpecificRuleWins(string $robots, string $path, bool $expectedAllowed) {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\n{$robots}\n");

		$this->assertSame($expectedAllowed, $parser->isAllowed($path));
		$this->assertSame(!$expectedAllowed, $parser->isDisallowed($path));
	}

	public function precedenceProvider(): array {
		// robots.txt body, path, expected isAllowed()
		return [
			'longer allow beats disallow'    => ["Disallow: /\nAllow: /p", '/page', true],
			'longer disallow beats allow'    => ["Allow: /a\nDisallow: /a/b", '/a/b/c', false],
			'equal length, allow wins'       => ["Disallow: /folder\nAllow: /folder", '/folder/page', true],
			'equal length, disallow first'   => ["Allow: /page\nDisallow: /page", '/page', true],
			'allow all next to disallow all' => ["Disallow: /\nAllow: /", '/anything', true],
			'end anchor is more specific'    => ["Disallow: /\nAllow: /\$", '/', true],
			'end anchor does not spill over' => ["Disallow: /\nAllow: /\$", '/page.htm', false],
			'no rule matches'                => ["Disallow: /admin", '/public', true],
			'nested allow inside disallow'   => ["Disallow: /admin\nAllow: /admin/public", '/admin/public/x', true],
			'nested allow inside disallow 2' => ["Disallow: /admin\nAllow: /admin/public", '/admin/secret', false],
		];
	}
}
