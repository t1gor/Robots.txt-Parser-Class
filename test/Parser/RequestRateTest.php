<?php declare(strict_types=1);

namespace Parser;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Parser\RequestRate;

/**
 * @covers \t1gor\RobotsTxtParser\Parser\RequestRate
 */
class RequestRateTest extends TestCase {

	/**
	 * @dataProvider provideValid
	 */
	public function testParsesAndWritesBackTheLargestUnitThatFits(string $given, string $expected) {
		$rate = RequestRate::tryParse($given);

		$this->assertNotNull($rate);
		$this->assertSame($expected, (string) $rate);
	}

	public function provideValid(): array {
		return [
			'seconds by default' => ['1/5', '1/5'],
			'seconds to minutes' => ['1/300', '1/5m'],
			'minutes kept'       => ['1/5m', '1/5m'],
			'hours'              => ['2/3600', '2/1h'],
			'days'               => ['3/86400', '3/1d'],
			'uppercase unit'     => ['1/5M', '1/5m'],
			'with a window'      => ['1/5m 0600-0845', '1/5m 0600-0845'],
			'spaced'             => [' 10 / 1 h ', '10/1h'],
		];
	}

	/**
	 * @dataProvider provideInvalid
	 */
	public function testRejectsWhatIsNotARate(string $given) {
		$this->assertNull(RequestRate::tryParse($given));
	}

	public function provideInvalid(): array {
		return [
			'no period'        => ['15686'],
			'not a number'     => ['100/bgdndgnd'],
			'no documents'     => ['0/5'],
			'no time at all'   => ['1/0'],
			'unknown unit'     => ['1/5y'],
			'unreadable window'=> ['1/5m 0600'],
			'nonsense'         => ['ngdndganda'],
			'empty'            => [''],
		];
	}

	public function testTheRateItself() {
		$rate = RequestRate::tryParse('4/1m 0600-0845');

		$this->assertSame(4, $rate->getDocuments());
		$this->assertSame(60, $rate->getSeconds());
		$this->assertSame(15.0, $rate->getSecondsPerRequest());
		$this->assertSame('0600-0845', (string) $rate->getWindow());
	}

	public function testARateWithoutAWindowAlwaysApplies() {
		$rate = RequestRate::tryParse('1/5m');

		$this->assertNull($rate->getWindow());
		$this->assertTrue($rate->appliesAt(new \DateTimeImmutable('2026-09-24 03:00:00 UTC')));
	}

	public function testARateAppliesOnlyWithinItsWindow() {
		$rate = RequestRate::tryParse('1/5m 0600-0845');

		$this->assertTrue($rate->appliesAt(new \DateTimeImmutable('2026-09-24 07:00:00 UTC')));
		$this->assertFalse($rate->appliesAt(new \DateTimeImmutable('2026-09-24 09:00:00 UTC')));
	}
}
