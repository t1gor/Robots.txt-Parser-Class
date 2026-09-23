<?php declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\Stream\GeneratorBasedReader;

/**
 * An encoding that is a typo, uninstalled, or simply not what the file is in must be
 * ignored quietly - never a PHP warning, and never a silently empty rule set.
 *
 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/70
 *
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::setEncoding
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::quietly
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::conversionErrors
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::removeEncodingFilter
 */
class InvalidEncodingTest extends TestCase {

	private const CONTENT = "User-agent: *\nDisallow: /admin\n";

	private function handlerFor(RobotsTxtParser $parser): TestHandler {
		$log = new Logger(static::class);
		$log->pushHandler($handler = new TestHandler(LogLevel::DEBUG));
		$parser->setLogger($log);

		return $handler;
	}

	/** @return string[] messages PHP raised while $run executed */
	private function phpWarningsDuring(callable $run): array {
		$raised = [];
		set_error_handler(function (int $number, string $message) use (&$raised): bool {
			$raised[] = $message;

			return true;
		});

		try {
			$run();
		} finally {
			restore_error_handler();
		}

		return $raised;
	}

	public function unusableEncodingProvider(): array {
		return [
			'invalid'      => ['UTF9'],
			'typo'         => ['ASCI'],
			'missing dash' => ['ISO8859'],
			'nonsense'     => ['not-an-encoding-at-all'],
			// iconv knows this one, but the file is not in it - fails at read time, not on setup
			'wrong charset' => ['UTF-16'],
		];
	}

	/**
	 * @dataProvider unusableEncodingProvider
	 */
	public function testRaisesNoPhpWarning(string $encoding) {
		$rules = [];
		$raised = $this->phpWarningsDuring(function () use ($encoding, &$rules) {
			$parser = new RobotsTxtParser(self::CONTENT, $encoding);
			$rules  = $parser->getRules();
		});

		$this->assertSame([], $raised, 'no PHP warning expected');
		$this->assertSame(['/admin'], $rules['*']['disallow'], 'content is still parsed as-is');
	}

	/**
	 * Rules must survive: robots.txt directives are ASCII even inside a mis-declared file,
	 * so reading it undecoded still yields usable rules rather than allowing everything.
	 *
	 * @dataProvider unusableEncodingProvider
	 */
	public function testRulesStillApply(string $encoding) {
		$parser = new RobotsTxtParser(self::CONTENT, $encoding);

		$this->assertTrue($parser->isDisallowed('/admin'));
		$this->assertTrue($parser->isAllowed('/public'));
	}

	/**
	 * @dataProvider unusableEncodingProvider
	 */
	public function testIsLoggedWithTheUnderlyingCause(string $encoding) {
		$parser  = new RobotsTxtParser(self::CONTENT, $encoding);
		$handler = $this->handlerFor($parser);
		$parser->getRules();

		$records = array_values(array_filter($handler->getRecords(), function (LogRecord $record): bool {
			return strpos($record['message'], 'Unsupported encoding') === 0;
		}));

		$this->assertCount(1, $records, stringifyLogs($handler->getRecords()));
		$this->assertSame(LogLevel::WARNING, strtolower($records[0]['level_name']));
		$this->assertSame($encoding, $records[0]['context']['encoding']);
		$this->assertNotSame('', $records[0]['context']['errors'], 'the cause must stay traceable');
	}

	public function utf8SpellingProvider(): array {
		return [['UTF-8'], ['utf-8'], ['utf8'], ['UTF8'], ['utf_8'], [' UTF-8 '], ['']];
	}

	/**
	 * Servers send "charset=utf8" as often as "utf-8"; neither needs converting.
	 *
	 * @dataProvider utf8SpellingProvider
	 */
	public function testUtf8SpellingsAreANoOp(string $encoding) {
		$parser  = new RobotsTxtParser(self::CONTENT, $encoding);
		$handler = $this->handlerFor($parser);
		$parser->getRules();

		$this->assertFalse(
			$handler->hasRecordThatContains('Adding encoding filter', Level::Debug),
			stringifyLogs($handler->getRecords())
		);
		$this->assertSame(['/admin'], $parser->getRules()['*']['disallow']);
	}

	/** A usable encoding logs no complaint at all. */
	public function testUsableEncodingIsNotReportedAsUnsupported() {
		$parser  = new RobotsTxtParser(fopen(__DIR__ . '/Fixtures/cp1251-real-bytes.txt', 'r'), 'Windows-1251');
		$handler = $this->handlerFor($parser);
		$parser->getRules();

		$this->assertTrue($handler->hasRecord('Adding encoding filter convert.iconv.Windows-1251/utf-8', Level::Debug));
		$this->assertFalse($handler->hasRecordThatContains('Unsupported encoding', Level::Warning));
	}

	/** The bytes really are converted, not just declared. */
	public function testWindows1251ContentIsDecoded() {
		$parser = new RobotsTxtParser(fopen(__DIR__ . '/Fixtures/cp1251-real-bytes.txt', 'r'), 'Windows-1251');

		$this->assertSame([
			'disallow' => ['/каталог', '/поиск'],
			'allow'    => ['/каталог/общий'],
		], $parser->getRules()['*']);

		$this->assertTrue($parser->isDisallowed('/каталог'));
		$this->assertTrue($parser->isAllowed('/каталог/общий'));
	}

	/** Without the conversion those bytes are not valid UTF-8 at all. */
	public function testSameFixtureUndecodedDoesNotYieldCyrillicRules() {
		$parser = new RobotsTxtParser(fopen(__DIR__ . '/Fixtures/cp1251-real-bytes.txt', 'r'));

		$this->assertNotContains('/каталог', $parser->getRules()['*']['disallow'] ?? []);
	}

	/**
	 * buildTree() re-runs while the tree is empty, so setEncoding() is reached repeatedly.
	 *
	 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/70
	 */
	public function testRepeatedParsingDoesNotStackEncodingFilters() {
		$parser = new RobotsTxtParser("# just a comment\n", 'Windows-1251');
		$reader = $this->readerOf($parser);

		$seen = [];
		for ($i = 0; $i < 3; $i++) {
			$parser->getRules();
			$seen[] = (int) $this->encodingFilterOf($reader);
		}

		$this->assertCount(1, array_unique($seen), 'the same filter must be reused');
		$this->assertSame(
			['convert.iconv.Windows-1251/utf-8'],
			array_values(array_filter($reader->filters(), function (string $name): bool {
				return strpos($name, 'convert.iconv') === 0;
			}))
		);
	}

	/** Switching encoding replaces the filter rather than layering a second conversion. */
	public function testChangingEncodingReplacesTheFilter() {
		$reader = GeneratorBasedReader::fromString(self::CONTENT);
		$reader->setEncoding('Windows-1251');
		$first = $reader->filters();

		$reader->setEncoding('KOI8-R');
		$second = $reader->filters();

		$this->assertSame(['convert.iconv.Windows-1251/utf-8'], array_slice($first, 0, 1));
		$this->assertSame(['convert.iconv.KOI8-R/utf-8'], array_slice($second, 0, 1));
		$this->assertCount(count($first), $second, 'no extra filter left behind');
	}

	/**
	 * A charset iconv knows, but that the file is not in, converts cleanly to garbage -
	 * iconv reports success, so nothing distinguishes it from valid content. Every rule
	 * is then lost and the parser fails open.
	 *
	 * @group known-issues
	 */
	public function testCharsetThatDecodesToGarbageDoesNotDiscardEveryRule() {
		$parser = new RobotsTxtParser(self::CONTENT, 'OSF10020402');

		$this->assertTrue($parser->isDisallowed('/admin'));
	}

	private function readerOf(RobotsTxtParser $parser): GeneratorBasedReader {
		$property = new ReflectionProperty(RobotsTxtParser::class, 'reader');
		$property->setAccessible(true);

		return $property->getValue($parser);
	}

	/** @return resource|null */
	private function encodingFilterOf(GeneratorBasedReader $reader) {
		$property = new ReflectionProperty(GeneratorBasedReader::class, 'encodingFilter');
		$property->setAccessible(true);

		return $property->getValue($reader);
	}
}
