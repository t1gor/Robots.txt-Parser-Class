<?php declare(strict_types=1);

namespace Config;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Config\ConfigurationFactory;
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\Logger\BufferedLogger;
use t1gor\RobotsTxtParser\Exception\ByteCountOutOfRangeException;
use t1gor\RobotsTxtParser\Exception\InvalidByteCountException;
use t1gor\RobotsTxtParser\Exception\UnknownOptionException;

/**
 * @covers \t1gor\RobotsTxtParser\Config\ConfigurationFactory
 * @covers \t1gor\RobotsTxtParser\Exception\ConfigurationExceptionFactory
 * @covers \t1gor\RobotsTxtParser\Exception\UnknownOptionException
 * @covers \t1gor\RobotsTxtParser\Exception\InvalidByteCountException
 */
class ConfigurationFactoryTest extends TestCase {

	public function testFromArrayWithoutKeysKeepsDefaults() {
		$this->assertSame(512000, ConfigurationFactory::fromArray([])->byteLimit);
	}

	/**
	 * @dataProvider acceptedArrayValues
	 */
	public function testFromArrayAcceptsWhatConfigLayersActuallyHandOver($given, ?int $expected) {
		$this->assertSame($expected, ConfigurationFactory::fromArray(['byte_limit' => $given])->byteLimit);
	}

	public function acceptedArrayValues(): array {
		return [
			'int'                 => [100000, 100000],
			'numeric string'      => ['100000', 100000],
			'padded string'       => ["  100000\n", 100000],
			'null'               => [null, null],
			'none'                => ['none', null],
			'unlimited'           => ['UNLIMITED', null],
		];
	}

	/**
	 * @dataProvider refusedArrayValues
	 */
	public function testFromArrayRefusesEverythingElse($given) {
		$this->expectException(InvalidByteCountException::class);

		ConfigurationFactory::fromArray(['byte_limit' => $given]);
	}

	public function refusedArrayValues(): array {
		return [
			'bool must never cast to 1' => [true],
			'float'                     => [1024.5],
			'non-integral string'       => ['5.5'],
			'exponent notation'         => ['1e3'],
			'array'                     => [[1024]],
			'random words'              => ['plenty'],
		];
	}

	public function testUnknownOptionNamesWhatIsAvailable() {
		$this->expectException(UnknownOptionException::class);
		$this->expectExceptionMessage('Unknown configuration option "byte_limitt"');

		ConfigurationFactory::fromArray(['byte_limitt' => 100000]);
	}

	public function testUnknownOptionListsTheKnownOnes() {
		$this->expectException(UnknownOptionException::class);
		$this->expectExceptionMessage('Known options: byte_limit.');

		ConfigurationFactory::fromArray(['something_entirely_different' => 1]);
	}

	public function testFromEnvironmentWithNothingSetKeepsDefaults() {
		$this->assertSame(512000, ConfigurationFactory::fromEnvironment([])->byteLimit);
	}

	/**
	 * @dataProvider environmentValues
	 */
	public function testFromEnvironmentReadsThePrefixedVariable($given, ?int $expected) {
		$config = ConfigurationFactory::fromEnvironment(['RTP_BYTE_LIMIT' => $given]);

		$this->assertSame($expected, $config->byteLimit);
	}

	public function environmentValues(): array {
		return [
			'env vars are strings' => ['100000', 100000],
			'blank means unset'    => ['', 512000],
			'whitespace only'      => ['   ', 512000],
			'none'                 => ['none', null],
			'already an int'       => [100000, 100000],
		];
	}

	public function testFromEnvironmentRefusesGarbage() {
		$this->expectException(InvalidByteCountException::class);

		ConfigurationFactory::fromEnvironment(['RTP_BYTE_LIMIT' => 'lots']);
	}

	/**
	 * Every other case injects an array, so this is the only one touching the real environment.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testFromEnvironmentReadsAnActualEnvironmentVariable() {
		putenv('RTP_BYTE_LIMIT=100000');

		$this->assertSame(100000, ConfigurationFactory::fromEnvironment()->byteLimit);
	}

	/**
	 * A constant only answers when no environment variable does.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testTheEnvironmentWinsOverAConstant() {
		putenv('RTP_BYTE_LIMIT=100000');
		define('RTP_BYTE_LIMIT', 200000);

		$this->assertSame(100000, ConfigurationFactory::fromEnvironment()->byteLimit);
	}

	/**
	 * WordPress has no environment convention - wp-config.php defines constants.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function testFromEnvironmentFallsBackToADefinedConstant() {
		define('RTP_BYTE_LIMIT', 100000);

		$this->assertSame(100000, ConfigurationFactory::fromEnvironment()->byteLimit);
	}

	/**
	 * @dataProvider unusableLimits
	 */
	public function testValidateRefusesLimitsThatCannotMeanAnything(int $given) {
		$this->expectException(ByteCountOutOfRangeException::class);
		$this->expectExceptionMessage('must be a positive number of bytes');

		ConfigurationFactory::validate(new Configuration($given));
	}

	public function unusableLimits(): array {
		return ['zero' => [0], 'negative' => [-1]];
	}

	public function testValidateAcceptsAPositiveLimitAndNoLimitAtAll() {
		ConfigurationFactory::validate(new Configuration(100000));
		ConfigurationFactory::validate(new Configuration(null));

		$this->expectNotToPerformAssertions();
	}

	public function testFromArrayRefusesAZeroLimitToo() {
		$this->expectException(ByteCountOutOfRangeException::class);

		ConfigurationFactory::fromArray(['byte_limit' => 0]);
	}

	private function warningsFor(?int $byteLimit): array {
		$logger = new BufferedLogger();

		ConfigurationFactory::validate(new Configuration($byteLimit), $logger);

		return $logger->getRecords();
	}

	public function testTheDefaultIsWorthNoWarnings() {
		$this->assertSame([], $this->warningsFor(Configuration::DEFAULT_BYTE_LIMIT));
		$this->assertSame([], $this->warningsFor(Configuration::RECOMMENDED_MIN_BYTE_LIMIT));
	}

	public function testDisablingTheLimitIsWarnedAbout() {
		$warnings = $this->warningsFor(null);

		$this->assertCount(1, $warnings);
		$this->assertSame(LogLevel::WARNING, $warnings[0]['level']);
		$this->assertStringContainsString('exhaustion', $warnings[0]['message']);
		$this->assertSame([Configuration::OPTION_BYTE_LIMIT => null], $warnings[0]['context']);
	}

	public function testALowLimitIsKeptButWarnedAbout() {
		$warnings = $this->warningsFor(1024);

		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('below the recommended minimum', $warnings[0]['message']);
		$this->assertSame(1024, $warnings[0]['context'][Configuration::OPTION_BYTE_LIMIT]);
	}

	public function testValidateWithoutALoggerStillRefusesTheUnusable() {
		$this->expectException(ByteCountOutOfRangeException::class);

		ConfigurationFactory::validate(new Configuration(0));
	}
}
