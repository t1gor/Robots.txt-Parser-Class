<?php declare(strict_types=1);

namespace Parser;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Parser\TimeWindow;

/**
 * @covers \t1gor\RobotsTxtParser\Parser\TimeWindow
 */
class TimeWindowTest extends TestCase {

	/**
	 * @dataProvider provideValid
	 */
	public function testParsesAndWritesBackTheSameWindow(string $given, string $expected) {
		$window = TimeWindow::tryParse($given);

		$this->assertNotNull($window);
		$this->assertSame($expected, (string) $window);
	}

	public function provideValid(): array {
		return [
			'plain'          => ['0600-0845', '0600-0845'],
			'padded'         => [' 0600-0845 ', '0600-0845'],
			'spaced hyphen'  => ['0600 - 0845', '0600-0845'],
			'over midnight'  => ['2300-0200', '2300-0200'],
			'whole day'      => ['0000-2359', '0000-2359'],
		];
	}

	/**
	 * @dataProvider provideInvalid
	 */
	public function testRejectsWhatIsNotATimeOfDay(string $given) {
		$this->assertNull(TimeWindow::tryParse($given));
	}

	public function provideInvalid(): array {
		return [
			'hour 25'        => ['2500-2600'],
			'minute 60'      => ['0060-0100'],
			'clock notation' => ['06:00-08:45'],
			'one sided'      => ['0600'],
			'not a time'     => ['morning'],
			'empty'          => [''],
		];
	}

	public function testEnds() {
		$window = TimeWindow::tryParse('0600-0845');

		$this->assertSame('0600', $window->getFrom());
		$this->assertSame('0845', $window->getTo());
	}

	/**
	 * @dataProvider provideMoments
	 */
	public function testCovers(string $window, string $moment, bool $covered) {
		$this->assertSame($covered, TimeWindow::tryParse($window)->covers(new \DateTimeImmutable($moment)));
	}

	public function provideMoments(): array {
		return [
			'inside'              => ['0600-0845', '2026-09-24 07:00:00 UTC', true],
			'on the opening edge' => ['0600-0845', '2026-09-24 06:00:00 UTC', true],
			'on the closing edge' => ['0600-0845', '2026-09-24 08:45:00 UTC', true],
			'before'              => ['0600-0845', '2026-09-24 05:59:00 UTC', false],
			'after'               => ['0600-0845', '2026-09-24 08:46:00 UTC', false],
			'late, over midnight' => ['2300-0200', '2026-09-24 23:30:00 UTC', true],
			'early, over midnight'=> ['2300-0200', '2026-09-24 01:00:00 UTC', true],
			'gap, over midnight'  => ['2300-0200', '2026-09-24 12:00:00 UTC', false],
		];
	}

	/**
	 * @dataProvider provideOpenings
	 */
	public function testNextOpening(string $window, string $after, string $expected) {
		$opening = TimeWindow::tryParse($window)->nextOpening(new \DateTimeImmutable($after, new \DateTimeZone('UTC')));

		$this->assertSame($expected, $opening->format('Y-m-d H:i:s T'));
	}

	public function provideOpenings(): array {
		return [
			// a window that is open is open now, so there is nothing to wait for
			'already open'          => ['0600-0845', '2026-09-25 07:00:00', '2026-09-25 07:00:00 UTC'],
			'opens later today'     => ['0600-0845', '2026-09-25 05:00:00', '2026-09-25 06:00:00 UTC'],
			'gone for today'        => ['0600-0845', '2026-09-25 09:00:00', '2026-09-26 06:00:00 UTC'],
			'on the opening edge'   => ['0600-0845', '2026-09-25 06:00:00', '2026-09-25 06:00:00 UTC'],
			'just shut'             => ['0600-0845', '2026-09-25 08:46:00', '2026-09-26 06:00:00 UTC'],
			// over midnight: still open in the small hours, and tonight's opening is what follows
			'over midnight, before' => ['2300-0200', '2026-09-25 12:00:00', '2026-09-25 23:00:00 UTC'],
			'over midnight, late'   => ['2300-0200', '2026-09-25 23:30:00', '2026-09-25 23:30:00 UTC'],
			'over midnight, early'  => ['2300-0200', '2026-09-25 01:00:00', '2026-09-25 01:00:00 UTC'],
			'over midnight, shut'   => ['2300-0200', '2026-09-25 02:30:00', '2026-09-25 23:00:00 UTC'],
		];
	}

	/** Whatever zone the caller keeps time in, the answer comes back in the one the spec uses. */
	public function testNextOpeningAnswersInUtc() {
		$berlin  = new \DateTimeImmutable('2026-09-25 12:00:00', new \DateTimeZone('Europe/Berlin'));
		$opening = TimeWindow::tryParse('0600-0845')->nextOpening($berlin);

		// 12:00 Berlin is 10:00 UTC, so today's window has gone
		$this->assertSame('2026-09-26 06:00:00 UTC', $opening->format('Y-m-d H:i:s T'));
	}

	/** A window opens on the minute, so nothing of the moment asked about carries over. */
	public function testAnOpeningHasNoSecondsLeftOnIt() {
		$opening = TimeWindow::tryParse('0600-0845')
			->nextOpening(new \DateTimeImmutable('2026-09-25 05:30:45.123456', new \DateTimeZone('UTC')));

		$this->assertSame('2026-09-25 06:00:00.000000', $opening->format('Y-m-d H:i:s.u'));
	}

	/** The spec says UTC, so a moment in another zone is compared as the UTC time it is. */
	public function testMomentsAreComparedInUtc() {
		$window = TimeWindow::tryParse('0600-0845');

		$this->assertTrue($window->covers(new \DateTimeImmutable('2026-09-24 09:00:00', new \DateTimeZone('Europe/Berlin'))));
		$this->assertFalse($window->covers(new \DateTimeImmutable('2026-09-24 07:00:00', new \DateTimeZone('Europe/Berlin'))));
	}
}
