<?php declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::bound
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::trimToLastLine
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::wasTruncated
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::emitWarnings
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::isTruncated
 */
class ByteLimitTest extends TestCase {

	/** Well clear of the recommended minimum, so these tests never trip the low-limit warning. */
	private const LIMIT = Configuration::RECOMMENDED_MIN_BYTE_LIMIT;

	/** @return resource */
	private function streamOf(string $content) {
		$stream = fopen('php://memory', 'r+');

		fwrite($stream, $content);
		rewind($stream);

		return $stream;
	}

	/** Valid robots.txt of exactly $bytes bytes, ending on a line break. */
	private function robotsTxtOf(int $bytes): string {
		$head    = "User-agent: *\n";
		$padding = $bytes - strlen($head) - strlen("Disallow: /\n");

		return $head . 'Disallow: /' . str_repeat('x', $padding) . "\n";
	}

	private function handlerFor(RobotsTxtParser $parser): TestHandler {
		$handler = new TestHandler(LogLevel::DEBUG);
		$logger  = new Logger(static::class);
		$logger->pushHandler($handler);

		// deliberately after construction - that is when a real caller gets to it
		$parser->setLogger($logger);

		return $handler;
	}

	public function testRulesPastTheLimitAreIgnored() {
		$content = $this->robotsTxtOf(self::LIMIT) . "Disallow: /beyond-the-limit\n";
		$parser  = new RobotsTxtParser($this->streamOf($content), config: new Configuration(self::LIMIT));

		$this->assertTrue($parser->isTruncated());
		$this->assertNotContains('/beyond-the-limit', $parser->getRules()['*']['disallow']);
	}

	public function testRulesBeforeTheLimitSurvive() {
		$content = $this->robotsTxtOf(self::LIMIT) . "Disallow: /beyond-the-limit\n";
		$parser  = new RobotsTxtParser($this->streamOf($content), config: new Configuration(self::LIMIT));

		$this->assertCount(1, $parser->getRules()['*']['disallow']);
		$this->assertStringStartsWith('/xxx', $parser->getRules()['*']['disallow'][0]);
	}

	/** A cut mid-rule must drop the rule, never shorten it into a broader one. */
	public function testAPartialLineIsDroppedRatherThanShortened() {
		$content = $this->robotsTxtOf(self::LIMIT - 10) . "Disallow: /secret-area\n";
		$parser  = new RobotsTxtParser($this->streamOf($content), config: new Configuration(self::LIMIT));

		foreach ($parser->getRules()['*']['disallow'] as $rule) {
			$this->assertStringNotContainsString('secret', $rule);
			$this->assertStringNotContainsString('Disallow', $rule);
		}
	}

	public function testContentWithoutAnyLineBreakIsDiscardedWholesale() {
		$parser = new RobotsTxtParser(
			$this->streamOf('Disallow: /' . str_repeat('x', self::LIMIT * 2)),
			config: new Configuration(self::LIMIT)
		);

		$this->assertTrue($parser->isTruncated());
		$this->assertSame([], $parser->getRules());
	}

	public function testDisablingTheLimitReadsEverything() {
		$content = $this->robotsTxtOf(self::LIMIT) . "Disallow: /beyond-the-limit\n";
		$parser  = new RobotsTxtParser($this->streamOf($content), config: new Configuration(null));

		$this->assertFalse($parser->isTruncated());
		$this->assertContains('/beyond-the-limit', $parser->getRules()['*']['disallow']);
	}

	public function testOrdinaryFilesAreUntouchedByTheDefaultLimit() {
		$parser = new RobotsTxtParser(fopen(__DIR__ . '/Fixtures/allow-spec.txt', 'r'));

		$this->assertFalse($parser->isTruncated());
		$this->assertNotEmpty($parser->getRules());
	}

	/**
	 * The limit counts fetched bytes, as Google and RFC 9309 do - this fixture is 76 bytes of
	 * CP1251 that becomes 100 once decoded, so a 90 byte limit only fits if we count before iconv.
	 */
	public function testTheLimitCountsRawBytesNotDecodedOnes() {
		$parser = new RobotsTxtParser(
			fopen(__DIR__ . '/Fixtures/cp1251-real-bytes.txt', 'r'),
			'Windows-1251',
			config: new Configuration(90)
		);

		$this->assertFalse($parser->isTruncated());
		$this->assertSame([
			'disallow' => ['/каталог', '/поиск'],
			'allow'    => ['/каталог/общий'],
		], $parser->getRules()['*']);
	}

	public function testTruncationIsReportedToALoggerAttachedAfterConstruction() {
		$content = $this->robotsTxtOf(self::LIMIT) . "Disallow: /beyond-the-limit\n";
		$parser  = new RobotsTxtParser($this->streamOf($content), config: new Configuration(self::LIMIT));
		$handler = $this->handlerFor($parser);

		$parser->getRules();

		$this->assertTrue(
			$handler->hasRecordThatContains('ran past the configured byte limit', Level::Warning),
			stringifyLogs($handler->getRecords())
		);
	}

	public function testALowLimitWarnsBeforeItEverTruncates() {
		$parser  = new RobotsTxtParser(
			fopen(__DIR__ . '/Fixtures/allow-spec.txt', 'r'),
			config: new Configuration(1024)
		);
		$handler = $this->handlerFor($parser);

		$parser->getRules();

		$this->assertTrue(
			$handler->hasRecordThatContains('below the recommended minimum', Level::Warning),
			stringifyLogs($handler->getRecords())
		);
	}

	public function testDisablingTheLimitWarns() {
		$parser  = new RobotsTxtParser(
			fopen(__DIR__ . '/Fixtures/allow-spec.txt', 'r'),
			config: new Configuration(null)
		);
		$handler = $this->handlerFor($parser);

		$parser->getRules();

		$this->assertTrue(
			$handler->hasRecordThatContains('exhaustion', Level::Warning),
			stringifyLogs($handler->getRecords())
		);
	}

	/** The default must stay silent, or the warnings stop meaning anything. */
	public function testTheDefaultConfigurationWarnsAboutNothing() {
		$parser  = new RobotsTxtParser(fopen(__DIR__ . '/Fixtures/allow-spec.txt', 'r'));
		$handler = $this->handlerFor($parser);

		$parser->getRules();

		$this->assertFalse($handler->hasWarningRecords(), stringifyLogs($handler->getRecords()));
	}

	public function testConfigurationIsReadableBackOffTheParser() {
		$config = new Configuration(100000);
		$parser = new RobotsTxtParser($this->streamOf("User-agent: *\n"), config: $config);

		$this->assertSame($config, $parser->getConfiguration());
	}
}
