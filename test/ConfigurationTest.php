<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Configuration;

/**
 * @covers \t1gor\RobotsTxtParser\Configuration
 */
class ConfigurationTest extends TestCase {

	public function testItDefaultsToFiveHundredKib() {
		$this->assertSame(512000, (new Configuration())->byteLimit);
		$this->assertSame(512000, Configuration::DEFAULT_BYTE_LIMIT);
		$this->assertSame(24576, Configuration::RECOMMENDED_MIN_BYTE_LIMIT);
	}

	public function testItHoldsWhateverItIsGiven() {
		$this->assertSame(100000, (new Configuration(100000))->byteLimit);
		$this->assertNull((new Configuration(null))->byteLimit);
	}

	/** No rules of its own - everything is checked by the factory. */
	public function testItValidatesNothingItself() {
		$this->assertSame(0, (new Configuration(0))->byteLimit);
		$this->assertSame(-1, (new Configuration(-1))->byteLimit);
	}

	public function testItCannotBeChangedAfterwards() {
		$config = new Configuration();

		$this->expectException(\Error::class);

		$config->byteLimit = 1;
	}
}
