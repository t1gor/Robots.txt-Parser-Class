<?php declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Config\ConfigurationFactory;
use t1gor\RobotsTxtParser\Config\Option;
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\Exception\EncodingFailedException;
use t1gor\RobotsTxtParser\Exception\InvalidEncodingException;
use t1gor\RobotsTxtParser\Logger\BufferedLogger;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\Writer\StreamWriter;
use t1gor\RobotsTxtParser\Writer\StringWriter;

/**
 * Encoding is a Configuration setting, one for reading and one for writing, both falling back to
 * a default that is UTF-8 unless it says otherwise.
 *
 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/136
 *
 * @covers \t1gor\RobotsTxtParser\Configuration
 * @covers \t1gor\RobotsTxtParser\Config\ConfigurationFactory
 * @covers \t1gor\RobotsTxtParser\Config\Option
 * @covers \t1gor\RobotsTxtParser\Exception\ConfigurationExceptionFactory
 * @covers \t1gor\RobotsTxtParser\Exception\InvalidEncodingException
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::setContent
 * @covers \t1gor\RobotsTxtParser\Writer\AbstractWriter::__construct
 */
class EncodingConfigurationTest extends TestCase {

	private const CP1251 = __DIR__ . '/Fixtures/cp1251-real-bytes.txt';

	private const CYRILLIC_RULES = [
		'disallow' => ['/каталог', '/поиск'],
		'allow'    => ['/каталог/общий'],
	];

	public function testDefaultsToUtf8OnBothSides() {
		$config = new Configuration();

		$this->assertSame('UTF-8', Configuration::DEFAULT_ENCODING);
		$this->assertSame('UTF-8', $config->defaultEncoding);
		$this->assertNull($config->parseEncoding);
		$this->assertNull($config->writeEncoding);
		$this->assertSame('UTF-8', $config->encodingForParsing());
		$this->assertSame('UTF-8', $config->encodingForWriting());
	}

	public function testTheDefaultAnswersForWhicheverSideIsUnset() {
		$config = new Configuration(defaultEncoding: 'KOI8-R');

		$this->assertSame('KOI8-R', $config->encodingForParsing());
		$this->assertSame('KOI8-R', $config->encodingForWriting());
	}

	/** Reading and writing are separate decisions, so either overrides the default on its own. */
	public function testEachSideOverridesTheDefault() {
		$config = new Configuration(defaultEncoding: 'KOI8-R', parseEncoding: 'Windows-1251');

		$this->assertSame('Windows-1251', $config->encodingForParsing());
		$this->assertSame('KOI8-R', $config->encodingForWriting());

		$both = new Configuration(defaultEncoding: 'KOI8-R', parseEncoding: 'Windows-1251', writeEncoding: 'UTF-8');

		$this->assertSame('Windows-1251', $both->encodingForParsing());
		$this->assertSame('UTF-8', $both->encodingForWriting());
	}

	public function testItStaysImmutable() {
		$config = new Configuration();

		$this->expectException(\Error::class);

		$config->parseEncoding = 'KOI8-R';
	}

	public function testTheByteLimitIsStillTheFirstArgument() {
		$config = new Configuration(100000, 'KOI8-R');

		$this->assertSame(100000, $config->byteLimit);
		$this->assertSame('KOI8-R', $config->defaultEncoding);
	}

	public function testTheOptionNamesAreAnEnum() {
		$this->assertSame(
			['byte_limit', 'default_encoding', 'parse_encoding', 'write_encoding'],
			Option::names()
		);
		$this->assertSame('RTP_PARSE_ENCODING', Option::PARSE_ENCODING->envName());
		$this->assertSame(
			[Option::DEFAULT_ENCODING, Option::PARSE_ENCODING, Option::WRITE_ENCODING],
			Option::encodings()
		);
	}

	public function testFromArrayReadsAllThreeKeys() {
		$config = ConfigurationFactory::fromArray([
			'byte_limit'       => 100000,
			'default_encoding' => 'KOI8-R',
			'parse_encoding'   => 'Windows-1251',
			'write_encoding'   => 'UTF-8',
		]);

		$this->assertSame(100000, $config->byteLimit);
		$this->assertSame('KOI8-R', $config->defaultEncoding);
		$this->assertSame('Windows-1251', $config->encodingForParsing());
		$this->assertSame('UTF-8', $config->encodingForWriting());
	}

	public function testFromArrayWithoutEncodingKeysKeepsUtf8() {
		$config = ConfigurationFactory::fromArray(['byte_limit' => 100000]);

		$this->assertSame('UTF-8', $config->encodingForParsing());
		$this->assertSame('UTF-8', $config->encodingForWriting());
	}

	/**
	 * @dataProvider blankValues
	 */
	public function testBlankIsNotADecision($given) {
		$config = ConfigurationFactory::fromArray([
			'default_encoding' => $given,
			'parse_encoding'   => $given,
			'write_encoding'   => $given,
		]);

		$this->assertSame('UTF-8', $config->defaultEncoding);
		$this->assertNull($config->parseEncoding);
		$this->assertNull($config->writeEncoding);
	}

	public function blankValues(): array {
		return ['null' => [null], 'empty' => [''], 'whitespace only' => ["  \n"]];
	}

	public function testSurroundingWhitespaceIsTrimmedOff() {
		$config = ConfigurationFactory::fromArray(['parse_encoding' => "  Windows-1251\n"]);

		$this->assertSame('Windows-1251', $config->parseEncoding);
	}

	/**
	 * @dataProvider refusedEncodings
	 */
	public function testFromArrayRefusesAnythingThatIsNotACharsetName($given) {
		$this->expectException(InvalidEncodingException::class);
		$this->expectExceptionMessage('must be a charset name or null');

		ConfigurationFactory::fromArray(['parse_encoding' => $given]);
	}

	public function refusedEncodings(): array {
		return [
			'bool'   => [true],
			'int'    => [1251],
			'float'  => [8.0],
			'array'  => [['UTF-8']],
			'object' => [new stdClass()],
		];
	}

	public function testFromEnvironmentReadsThePrefixedVariables() {
		$config = ConfigurationFactory::fromEnvironment([
			'RTP_DEFAULT_ENCODING' => 'KOI8-R',
			'RTP_PARSE_ENCODING'   => 'Windows-1251',
			'RTP_WRITE_ENCODING'   => 'UTF-8',
		]);

		$this->assertSame('KOI8-R', $config->defaultEncoding);
		$this->assertSame('Windows-1251', $config->encodingForParsing());
		$this->assertSame('UTF-8', $config->encodingForWriting());
	}

	/**
	 * A real environment variable is always a string, but a WordPress constant is whatever
	 * wp-config.php defined - and the same rule has to judge it.
	 *
	 * @dataProvider refusedConstants
	 */
	public function testFromEnvironmentRefusesTheSameValuesFromArrayDoes($given) {
		$this->expectException(InvalidEncodingException::class);
		$this->expectExceptionMessage('must be a charset name or null');

		ConfigurationFactory::fromEnvironment(['RTP_PARSE_ENCODING' => $given]);
	}

	/** Only the scalars: nothing else can be an environment variable or a sensible constant. */
	public function refusedConstants(): array {
		return ['bool' => [true], 'int' => [1251], 'float' => [8.0]];
	}

	/** And what cannot be one at all reads as unset rather than as a mistake. */
	public function testANonScalarInTheEnvironmentIsSimplyNotThere() {
		$config = ConfigurationFactory::fromEnvironment(['RTP_PARSE_ENCODING' => ['UTF-8']]);

		$this->assertNull($config->parseEncoding);
	}

	/** The byte limit is the exception: an integer constant is exactly how WordPress writes one. */
	public function testAnIntegerByteLimitFromTheEnvironmentIsStillFine() {
		$this->assertSame(100000, ConfigurationFactory::fromEnvironment(['RTP_BYTE_LIMIT' => 100000])->byteLimit);
	}

	public function testFromEnvironmentWithNothingSetKeepsUtf8() {
		$config = ConfigurationFactory::fromEnvironment([]);

		$this->assertSame('UTF-8', $config->encodingForParsing());
		$this->assertSame('UTF-8', $config->encodingForWriting());
	}

	/**
	 * The only case here touching the real environment; every other one injects an array.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testFromEnvironmentReadsAnActualEnvironmentVariable() {
		putenv('RTP_PARSE_ENCODING=Windows-1251');

		$this->assertSame('Windows-1251', ConfigurationFactory::fromEnvironment()->encodingForParsing());
	}

	/**
	 * WordPress has no environment convention - wp-config.php defines constants.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testFromEnvironmentFallsBackToADefinedConstant() {
		define('RTP_PARSE_ENCODING', 'Windows-1251');

		$this->assertSame('Windows-1251', ConfigurationFactory::fromEnvironment()->encodingForParsing());
	}

	/** @return array<int, array{message: string, context: array}> */
	private function warningsFor(Configuration $config): array {
		$logger = new BufferedLogger();

		ConfigurationFactory::validate($config, $logger);

		return $logger->getRecords();
	}

	/** A configuration built by the factory reports its own warnings, given somewhere to report to. */
	public function testTheFactoryReportsThroughALoggerItIsGiven() {
		$logger = new BufferedLogger();

		ConfigurationFactory::fromArray(['write_encoding' => 'UTF9'], $logger);

		$this->assertCount(1, $logger->getRecords());
		$this->assertStringContainsString('not a charset iconv knows', $logger->getRecords()[0]['message']);
	}

	public function testTheEnvironmentEntryPointReportsToo() {
		$logger = new BufferedLogger();

		ConfigurationFactory::fromEnvironment(['RTP_PARSE_ENCODING' => 'UTF9'], $logger);

		$this->assertCount(1, $logger->getRecords());
	}

	/** Without one it stays silent, as there is nowhere to say it. */
	public function testTheFactoryIsSilentWithoutALogger() {
		$logger = new BufferedLogger();

		ConfigurationFactory::fromArray(['write_encoding' => 'UTF9']);

		$this->assertSame([], $logger->getRecords());
	}

	/** The byte limit goes through the same path, so it reports from the factory too. */
	public function testTheByteLimitWarningReachesTheFactoryLoggerAsWell() {
		$logger = new BufferedLogger();

		ConfigurationFactory::fromArray(['byte_limit' => 1024], $logger);

		$this->assertCount(1, $logger->getRecords());
		$this->assertStringContainsString('below the recommended minimum', $logger->getRecords()[0]['message']);
	}

	public function testAUsableCharsetIsWorthNoWarning() {
		$this->assertSame([], $this->warningsFor(new Configuration()));
		$this->assertSame([], $this->warningsFor(new Configuration(parseEncoding: 'Windows-1251', writeEncoding: 'KOI8-R')));
	}

	/**
	 * Warned rather than thrown: reading falls back to the bytes as they are, and a caller that
	 * only ever parses should not be stopped by a write setting it never reaches.
	 */
	public function testACharsetIconvDoesNotKnowIsWarnedAbout() {
		$warnings = $this->warningsFor(new Configuration(parseEncoding: 'UTF9'));

		$this->assertCount(1, $warnings);
		$this->assertSame(LogLevel::WARNING, $warnings[0]['level']);
		$this->assertStringContainsString('not a charset iconv knows', $warnings[0]['message']);
		$this->assertSame('UTF9', $warnings[0]['context']['encoding']);
		$this->assertSame([Option::PARSE_ENCODING->value], $warnings[0]['context']['options']);
	}

	public function testEachSideIsReportedOnItsOwn() {
		$warnings = $this->warningsFor(new Configuration(parseEncoding: 'UTF9', writeEncoding: 'ASCI'));

		$this->assertCount(2, $warnings);
		$this->assertSame('UTF9', $warnings[0]['context']['encoding']);
		$this->assertSame([Option::PARSE_ENCODING->value], $warnings[0]['context']['options']);
		$this->assertSame('ASCI', $warnings[1]['context']['encoding']);
		$this->assertSame([Option::WRITE_ENCODING->value], $warnings[1]['context']['options']);
	}

	/** One mistake, one warning - and it names both options the bad default reached. */
	public function testAnUnusableDefaultIsReportedOnceNamingBothSides() {
		$warnings = $this->warningsFor(new Configuration(defaultEncoding: 'UTF9'));

		$this->assertCount(1, $warnings);
		$this->assertSame('UTF9', $warnings[0]['context']['encoding']);
		$this->assertSame(
			[Option::PARSE_ENCODING->value, Option::WRITE_ENCODING->value],
			$warnings[0]['context']['options']
		);
	}

	/** Same value set on both sides is one mistake too, and both are named. */
	public function testTheSameBadCharsetOnBothSidesIsReportedOnce() {
		$warnings = $this->warningsFor(new Configuration(parseEncoding: 'UTF9', writeEncoding: 'UTF9'));

		$this->assertCount(1, $warnings);
		$this->assertSame(
			[Option::PARSE_ENCODING->value, Option::WRITE_ENCODING->value],
			$warnings[0]['context']['options']
		);
	}

	public function testTheByteLimitIsStillWarnedAboutAlongsideIt() {
		$warnings = $this->warningsFor(new Configuration(1024, 'UTF9'));

		$this->assertCount(2, $warnings);
		$this->assertStringContainsString('below the recommended minimum', $warnings[0]['message']);
		$this->assertStringContainsString('not a charset iconv knows', $warnings[1]['message']);
	}

	/** Raised for the charset name, not for the document - nothing has been read yet. */
	public function testValidatingAnUnknownCharsetRaisesNoPhpWarning() {
		$raised = [];
		set_error_handler(function (int $number, string $message) use (&$raised): bool {
			$raised[] = $message;

			return true;
		});

		try {
			$this->warningsFor(new Configuration(parseEncoding: 'not-an-encoding-at-all'));
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised);
	}

	public function testTheParserReadsInTheConfiguredParseEncoding() {
		$parser = new RobotsTxtParser(new Configuration(parseEncoding: 'Windows-1251'));
		$parser->setContent(fopen(self::CP1251, 'r'));

		$this->assertSame(self::CYRILLIC_RULES, $parser->getRules()['*']);
	}

	/** Only one charset in mind, so the default covers reading too. */
	public function testTheDefaultEncodingReachesTheReader() {
		$parser = new RobotsTxtParser(new Configuration(defaultEncoding: 'Windows-1251'));
		$parser->setContent(fopen(self::CP1251, 'r'));

		$this->assertSame(self::CYRILLIC_RULES, $parser->getRules()['*']);
	}

	/** A write-only setting must not touch what is read. */
	public function testTheWriteEncodingIsNotAppliedToTheReader() {
		$parser = new RobotsTxtParser(new Configuration(writeEncoding: 'Windows-1251'));
		$parser->setContent(fopen(self::CP1251, 'r'));

		$this->assertNotContains('/каталог', $parser->getRules()['*']['disallow'] ?? []);
	}

	/** The parser is resolved once and handed round, so one document can still say otherwise. */
	public function testTheSetContentArgumentOverridesTheConfiguration() {
		$parser = new RobotsTxtParser(new Configuration(parseEncoding: 'KOI8-R'));
		$parser->setContent(fopen(self::CP1251, 'r'), 'Windows-1251');

		$this->assertSame(self::CYRILLIC_RULES, $parser->getRules()['*']);
	}

	/** And the override lasts only as long as that document. */
	public function testTheNextDocumentIsBackOnTheConfiguredEncoding() {
		$parser = new RobotsTxtParser(new Configuration(parseEncoding: 'Windows-1251'));

		$parser->setContent(fopen(self::CP1251, 'r'), 'UTF-8');
		$this->assertNotContains('/каталог', $parser->getRules()['*']['disallow'] ?? []);

		$parser->setContent(fopen(self::CP1251, 'r'));
		$this->assertSame(self::CYRILLIC_RULES, $parser->getRules()['*']);
	}

	public function testAConfigurationWithNoEncodingSetAddsNoFilter() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\n");
		$parser->getRules();

		$this->assertSame([], array_values(array_filter(
			$parser->getReader()->filters(),
			static fn (string $name): bool => str_starts_with($name, 'convert.iconv')
		)));
	}

	/** @return string what the writer put into a stream of its own */
	private function render(array $tree, ?Configuration $config, ?string $override = null): string {
		$out    = fopen('php://memory', 'r+');
		$writer = new StringWriter(null, $config);

		if (!is_null($override)) {
			$writer->setEncoding($override);
		}

		$writer->setTree($tree)->setEol("\n")->setOutput($out)->render();
		rewind($out);

		return (string) stream_get_contents($out);
	}

	private function tree(): array {
		return ['*' => ['disallow' => ['/каталог']]];
	}

	public function testTheWriterWritesInTheConfiguredWriteEncoding() {
		$document = $this->render($this->tree(), new Configuration(writeEncoding: 'Windows-1251'));

		$this->assertSame("User-agent: *\nDisallow: /каталог\n", iconv('Windows-1251', 'UTF-8', $document));
		$this->assertStringNotContainsString('каталог', $document, 'the bytes must not still be UTF-8');
	}

	public function testTheDefaultEncodingReachesTheWriter() {
		$document = $this->render($this->tree(), new Configuration(defaultEncoding: 'Windows-1251'));

		$this->assertSame("User-agent: *\nDisallow: /каталог\n", iconv('Windows-1251', 'UTF-8', $document));
	}

	/** A read-only setting must not touch what is written. */
	public function testTheParseEncodingIsNotAppliedToTheWriter() {
		$document = $this->render($this->tree(), new Configuration(parseEncoding: 'Windows-1251'));

		$this->assertSame("User-agent: *\nDisallow: /каталог\n", $document);
	}

	public function testNoConfigurationLeavesTheWriterOnUtf8() {
		$this->assertSame("User-agent: *\nDisallow: /каталог\n", $this->render($this->tree(), null));
	}

	public function testSetEncodingStillWinsOverTheConfiguration() {
		$document = $this->render($this->tree(), new Configuration(writeEncoding: 'Windows-1251'), 'KOI8-R');

		$this->assertSame("User-agent: *\nDisallow: /каталог\n", iconv('KOI8-R', 'UTF-8', $document));
	}

	/** And null puts it back to the UTF-8 the spec asks for. */
	public function testSetEncodingNullClearsTheConfiguredOne() {
		$document = $this->render($this->tree(), new Configuration(writeEncoding: 'Windows-1251'), 'UTF-8');

		$this->assertSame("User-agent: *\nDisallow: /каталог\n", $document);
	}

	public function testBothWritersTakeTheConfigurationTheSameWay() {
		$config = new Configuration(writeEncoding: 'Windows-1251');
		$out    = fopen('php://memory', 'r+');

		(new StreamWriter(null, $config))->setTree($this->tree())->setEol("\n")->setOutput($out)->render();
		rewind($out);

		$this->assertSame($this->render($this->tree(), $config), (string) stream_get_contents($out));
	}

	/** Warned once at construction, as setEncoding() is what says it. */
	public function testAConfiguredNonUtf8WriteEncodingIsWarnedAbout() {
		$log = new Logger(static::class);
		$log->pushHandler($handler = new TestHandler(LogLevel::DEBUG));

		new StringWriter($log, new Configuration(writeEncoding: 'Windows-1251'));

		$this->assertTrue(
			$handler->hasRecordThatContains('different from UTF-8', Level::Warning),
			stringifyLogs($handler->getRecords())
		);
	}

	/** A charset only warned about at config time still throws where it cannot work. */
	public function testAnUnusableWriteEncodingStillThrowsAtRenderTime() {
		$this->expectException(EncodingFailedException::class);

		$this->render($this->tree(), new Configuration(writeEncoding: 'UTF9'));
	}

	/** The whole point of two settings: read one charset, publish another. */
	public function testADocumentCanBeReadInOneCharsetAndWrittenInAnother() {
		$config = new Configuration(parseEncoding: 'Windows-1251', writeEncoding: 'KOI8-R');
		$parser = new RobotsTxtParser($config);
		$parser->setContent(fopen(self::CP1251, 'r'));

		$document = $this->render($parser->getRules(), $config);

		// longest rule first, allow ahead of an equally long disallow - see AbstractWriter::ordered()
		$this->assertSame(
			"User-agent: *\nAllow: /каталог/общий\nDisallow: /каталог\nDisallow: /поиск\n",
			iconv('KOI8-R', 'UTF-8', $document)
		);
	}

	/** The constant moved to Configuration; the old name still answers. */
	public function testTheParsersOwnConstantStillPointsAtUtf8() {
		$this->assertSame(Configuration::DEFAULT_ENCODING, RobotsTxtParser::DEFAULT_ENCODING);
	}
}
