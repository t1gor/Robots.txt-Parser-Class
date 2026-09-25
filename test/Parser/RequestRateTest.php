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

	/**
	 * @dataProvider providePeriods
	 */
	public function testThePeriodAsAnInterval(string $given, string $expected) {
		$this->assertSame($expected, RequestRate::tryParse($given)->getPeriod()->format('%hh %im %ss'));
	}

	public function providePeriods(): array {
		return [
			'seconds'        => ['1/45', '0h 0m 45s'],
			'minutes'        => ['1/5m', '0h 5m 0s'],
			'hours'          => ['2/1h', '1h 0m 0s'],
			'a day is hours' => ['3/1d', '24h 0m 0s'],
			'mixed'          => ['1/3661', '1h 1m 1s'],
		];
	}

	/** Whatever it is added to, the period has to stay the seconds the rate was written with. */
	public function testThePeriodIsAlwaysTheSameLength() {
		$moment = new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('UTC'));
		$rate   = RequestRate::tryParse('3/1d');

		$this->assertSame(
			$rate->getSeconds(),
			$moment->add($rate->getPeriod())->getTimestamp() - $moment->getTimestamp()
		);
	}

	/**
	 * P1D would land on the same clock time and so move by 25 hours over the autumn change; the
	 * rate means 86400 seconds, which is what PT24H stays.
	 */
	public function testThePeriodDoesNotDriftAcrossADstChange() {
		$berlin = new \DateTimeImmutable('2026-10-24 12:00:00', new \DateTimeZone('Europe/Berlin'));
		$rate   = RequestRate::tryParse('3/1d');

		$this->assertSame('2026-10-25 11:00 CET', $berlin->add($rate->getPeriod())->format('Y-m-d H:i T'));
		$this->assertSame(86400, $berlin->add($rate->getPeriod())->getTimestamp() - $berlin->getTimestamp());
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
