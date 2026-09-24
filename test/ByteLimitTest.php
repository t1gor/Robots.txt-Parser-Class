<?php declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\Exception\ByteCountOutOfRangeException;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::bound
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::trimToLastLine
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::wasTruncated
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
		$parser  = (new RobotsTxtParser(new Configuration(self::LIMIT)))->setContent($this->streamOf($content));

		$this->assertTrue($parser->getReader()->wasTruncated());
		$this->assertNotContains('/beyond-the-limit', $parser->getRules()['*']['disallow']);
	}

	public function testRulesBeforeTheLimitSurvive() {
		$content = $this->robotsTxtOf(self::LIMIT) . "Disallow: /beyond-the-limit\n";
		$parser  = (new RobotsTxtParser(new Configuration(self::LIMIT)))->setContent($this->streamOf($content));

		$this->assertCount(1, $parser->getRules()['*']['disallow']);
		$this->assertStringStartsWith('/xxx', $parser->getRules()['*']['disallow'][0]);
	}

	/** A cut mid-rule must drop the rule, never shorten it into a broader one. */
	public function testAPartialLineIsDroppedRatherThanShortened() {
		$content = $this->robotsTxtOf(self::LIMIT - 10) . "Disallow: /secret-area\n";
		$parser  = (new RobotsTxtParser(new Configuration(self::LIMIT)))->setContent($this->streamOf($content));

		foreach ($parser->getRules()['*']['disallow'] as $rule) {
			$this->assertStringNotContainsString('secret', $rule);
			$this->assertStringNotContainsString('Disallow', $rule);
		}
	}

	public function testContentWithoutAnyLineBreakIsDiscardedWholesale() {
		$parser = (new RobotsTxtParser(new Configuration(self::LIMIT)))->setContent($this->streamOf('Disallow: /' . str_repeat('x', self::LIMIT * 2)));

		$this->assertTrue($parser->getReader()->wasTruncated());
		$this->assertSame([], $parser->getRules());
	}

	public function testDisablingTheLimitReadsEverything() {
		$content = $this->robotsTxtOf(self::LIMIT) . "Disallow: /beyond-the-limit\n";
		$parser  = (new RobotsTxtParser(new Configuration(null)))->setContent($this->streamOf($content));

		$this->assertFalse($parser->getReader()->wasTruncated());
		$this->assertContains('/beyond-the-limit', $parser->getRules()['*']['disallow']);
	}

	public function testOrdinaryFilesAreUntouchedByTheDefaultLimit() {
		$parser = (new RobotsTxtParser())->setContent(fopen(__DIR__ . '/Fixtures/allow-spec.txt', 'r'));

		$this->assertFalse($parser->getReader()->wasTruncated());
		$this->assertNotEmpty($parser->getRules());
	}

	/**
	 * The limit counts fetched bytes, as Google and RFC 9309 do - this fixture is 76 bytes of
	 * CP1251 that becomes 100 once decoded, so a 90 byte limit only fits if we count before iconv.
	 */
	public function testTheLimitCountsRawBytesNotDecodedOnes() {
		$parser = (new RobotsTxtParser(new Configuration(90)))->setContent(fopen(__DIR__ . '/Fixtures/cp1251-real-bytes.txt', 'r'), 'Windows-1251');

		$this->assertFalse($parser->getReader()->wasTruncated());
		$this->assertSame([
			'disallow' => ['/каталог', '/поиск'],
			'allow'    => ['/каталог/общий'],
		], $parser->getRules()['*']);
	}

	public function testTruncationIsReportedToALoggerAttachedAfterConstruction() {
		$content = $this->robotsTxtOf(self::LIMIT) . "Disallow: /beyond-the-limit\n";
		$parser  = (new RobotsTxtParser(new Configuration(self::LIMIT)))->setContent($this->streamOf($content));
		$handler = $this->handlerFor($parser);

		$parser->getRules();

		$this->assertTrue(
			$handler->hasRecordThatContains('ran past the configured byte limit', Level::Warning),
			stringifyLogs($handler->getRecords())
		);
	}

	public function testALowLimitWarnsBeforeItEverTruncates() {
		$parser  = (new RobotsTxtParser(new Configuration(1024)))->setContent(fopen(__DIR__ . '/Fixtures/allow-spec.txt', 'r'));
		$handler = $this->handlerFor($parser);

		$parser->getRules();

		$this->assertTrue(
			$handler->hasRecordThatContains('below the recommended minimum', Level::Warning),
			stringifyLogs($handler->getRecords())
		);
	}

	public function testDisablingTheLimitWarns() {
		$parser  = (new RobotsTxtParser(new Configuration(null)))->setContent(fopen(__DIR__ . '/Fixtures/allow-spec.txt', 'r'));
		$handler = $this->handlerFor($parser);

		$parser->getRules();

		$this->assertTrue(
			$handler->hasRecordThatContains('exhaustion', Level::Warning),
			stringifyLogs($handler->getRecords())
		);
	}

	/** The default must stay silent, or the warnings stop meaning anything. */
	public function testTheDefaultConfigurationWarnsAboutNothing() {
		$parser  = (new RobotsTxtParser())->setContent(fopen(__DIR__ . '/Fixtures/allow-spec.txt', 'r'));
		$handler = $this->handlerFor($parser);

		$parser->getRules();

		$this->assertFalse($handler->hasWarningRecords(), stringifyLogs($handler->getRecords()));
	}

	/** Configuration validates nothing itself, so the parser has to. */
	public function testTheParserRefusesAHandBuiltConfigurationThatCannotWork() {
		$this->expectException(ByteCountOutOfRangeException::class);

		(new RobotsTxtParser(new Configuration(0)))->setContent($this->streamOf("User-agent: *\n"));
	}

	public function testConfigurationIsReadableBackOffTheParser() {
		$config = new Configuration(100000);
		$parser = (new RobotsTxtParser($config))->setContent($this->streamOf("User-agent: *\n"));

		$this->assertSame($config, $parser->getConfiguration());
	}
}
