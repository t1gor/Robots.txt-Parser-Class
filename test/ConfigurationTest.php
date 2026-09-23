<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\Exception\ConfigurationException;

/**
 * @covers \t1gor\RobotsTxtParser\Configuration
 */
class ConfigurationTest extends TestCase {

	public function testDefaultsToFiveHundredKibAndSaysNothing() {
		$config = new Configuration();

		$this->assertSame(512000, $config->byteLimit);
		$this->assertSame(512000, Configuration::DEFAULT_BYTE_LIMIT);
		$this->assertSame([], $config->warnings, 'the happy path must not log at all');
	}

	public function testLimitAtTheRecommendedMinimumIsAccepted() {
		$config = new Configuration(Configuration::RECOMMENDED_MIN_BYTE_LIMIT);

		$this->assertSame(24576, $config->byteLimit);
		$this->assertSame([], $config->warnings);
	}

	public function testLimitBelowTheRecommendedMinimumIsKeptButWarnedAbout() {
		$config = new Configuration(1024);

		$this->assertSame(1024, $config->byteLimit, 'a low limit is safe, just probably a mistake');
		$this->assertCount(1, $config->warnings);
		$this->assertStringContainsString('below the recommended minimum', $config->warnings[0]['message']);
		$this->assertSame(1024, $config->warnings[0]['context']['byte_limit']);
	}

	public function testDisablingTheLimitWarnsAboutResourceExhaustion() {
		$config = new Configuration(null);

		$this->assertNull($config->byteLimit);
		$this->assertCount(1, $config->warnings);
		$this->assertStringContainsString('exhaustion', $config->warnings[0]['message']);
	}

	public function testZeroIsRefused() {
		$this->expectException(ConfigurationException::class);
		$this->expectExceptionMessage('must be a positive number of bytes');

		new Configuration(0);
	}

	public function testNegativeIsRefused() {
		$this->expectException(ConfigurationException::class);

		new Configuration(-1);
	}

	public function testConfigurationExceptionStaysCatchableAsInvalidArgument() {
		$this->expectException(\InvalidArgumentException::class);

		new Configuration(0);
	}
}
