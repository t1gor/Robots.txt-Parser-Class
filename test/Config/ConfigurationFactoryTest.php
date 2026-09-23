<?php declare(strict_types=1);

namespace Config;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Config\ConfigurationFactory;
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\Exception\ConfigurationException;

/**
 * @covers \t1gor\RobotsTxtParser\Config\ConfigurationFactory
 * @covers \t1gor\RobotsTxtParser\Exception\ConfigurationExceptionFactory
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
		$this->expectException(ConfigurationException::class);

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

	public function testUnknownOptionSuggestsTheNearestOne() {
		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('Did you mean "byte_limit"?');

		ConfigurationFactory::fromArray(['byte_limitt' => 100000]);
	}

	public function testWildlyUnknownOptionListsWhatIsAvailable() {
		$this->expectException(ConfigurationException::class);
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
		$this->expectException(ConfigurationException::class);

		ConfigurationFactory::fromEnvironment(['RTP_BYTE_LIMIT' => 'lots']);
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
}
