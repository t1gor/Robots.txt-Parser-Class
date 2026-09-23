<?php declare(strict_types=1);

namespace Stream\Filter;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\Stream\Filters\SkipCommentedLinesFilter;

/**
 * A /u pattern returns null on invalid UTF-8. Unhandled, that blanked the bucket and was
 * then passed to the next filter as null - a fatal TypeError.
 *
 * @covers \t1gor\RobotsTxtParser\Stream\Filters\KeepsDataOnInvalidUtf8Trait
 */
class InvalidUtf8PassThroughTest extends TestCase {

	/** @dataProvider badByteProvider */
	public function testValidRulesSurviveABadByte(string $content, array $expected) {
		$parser = new RobotsTxtParser($content);

		$this->assertSame($expected, $parser->getRules()['*']['disallow'] ?? []);
	}

	public function badByteProvider(): array {
		return [
			'in a rule'     => ["User-agent: *\nDisallow: /a\nDisallow: /caf\xE9\nDisallow: /b\n", ['/a', '/b']],
			'in a comment'  => ["User-agent: *\n# caf\xE9\nDisallow: /a\n", ['/a']],
			'lone continuation' => ["User-agent: *\nDisallow: /a\n\x80\nDisallow: /b\n", ['/a', '/b']],
			'truncated utf8' => ["User-agent: *\nDisallow: /a\nDisallow: /\xC3\n", ['/a']],
		];
	}

	/** The whole point: it must not be fatal. */
	public function testBadByteIsNotFatal() {
		$parser = new RobotsTxtParser("User-agent: *\n# caf\xE9\nDisallow: /admin\n");

		$this->assertTrue($parser->isDisallowed('/admin'));
		$this->assertTrue($parser->isAllowed('/public'));
	}

	/** Clean input is still filtered normally - the fallback must not disable the filters. */
	public function testValidUtf8IsStillFiltered() {
		$parser = new RobotsTxtParser("User-agent: *\n# a comment\nDisallow: /admin # trailing\n");

		$this->assertSame(['/admin'], $parser->getRules()['*']['disallow']);
		$this->assertStringNotContainsString('#', $parser->getContent());
	}

	public function testTraitKeepsSubjectWhenPatternFails() {
		$filter = new class extends \php_user_filter {
			use \t1gor\RobotsTxtParser\Stream\Filters\KeepsDataOnInvalidUtf8Trait;

			public static function run(string $subject, ?int &$count): string {
				return self::replaceOrKeep('/^#.*/mui', '', $subject, $count);
			}
		};

		$count = null;
		$this->assertSame("# caf\xE9", $filter::run("# caf\xE9", $count), 'invalid UTF-8 is kept as-is');
		$this->assertSame(0, $count);

		$this->assertSame('', $filter::run('# clean', $count), 'valid UTF-8 still replaced');
		$this->assertSame(1, $count);
	}
}
