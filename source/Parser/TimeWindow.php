<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser;

/**
 * The "hhmm-hhmm" span Visit-time and Request-rate are written with, in UTC.
 *
 * @link http://www.conman.org/people/spc/robots2.html
 */
final class TimeWindow implements \Stringable {

	private const FORMAT = '/^([01]\d|2[0-3])([0-5]\d)\s*-\s*([01]\d|2[0-3])([0-5]\d)$/';

	/** Minutes since midnight, so a comparison is an integer one. */
	private function __construct(
		private readonly int $from,
		private readonly int $to
	) {
	}

	public static function tryParse(string $value): ?self {
		if (1 !== preg_match(self::FORMAT, trim($value), $parts)) {
			return null;
		}

		return new self(
			(int) $parts[1] * 60 + (int) $parts[2],
			(int) $parts[3] * 60 + (int) $parts[4]
		);
	}

	public function getFrom(): string {
		return self::format($this->from);
	}

	public function getTo(): string {
		return self::format($this->to);
	}

	/** True while the window is open. A window that ends before it starts runs over midnight. */
	public function covers(\DateTimeInterface $moment): bool {
		$utc     = \DateTimeImmutable::createFromInterface($moment)->setTimezone(new \DateTimeZone('UTC'));
		$minutes = (int) $utc->format('G') * 60 + (int) $utc->format('i');

		return $this->from <= $this->to
			? $minutes >= $this->from && $minutes <= $this->to
			: $minutes >= $this->from || $minutes <= $this->to;
	}

	/**
	 * The earliest moment from $after that the window is open - $after itself when it already is,
	 * so a scheduler can sleep until whatever comes back without checking first. UTC, like the rest
	 * of this, which is also what makes "+1 day" exactly 24 hours here.
	 */
	public function nextOpening(\DateTimeInterface $after): \DateTimeImmutable {
		$utc = \DateTimeImmutable::createFromInterface($after)->setTimezone(new \DateTimeZone('UTC'));

		if ($this->covers($utc)) {
			return $utc;
		}

		$opening = $utc->setTime(intdiv($this->from, 60), $this->from % 60);

		// today's opening, unless it has already gone by - which is also the answer over midnight
		return $opening > $utc ? $opening : $opening->modify('+1 day');
	}

	public function __toString(): string {
		return $this->getFrom() . '-' . $this->getTo();
	}

	private static function format(int $minutes): string {
		return sprintf('%02d%02d', intdiv($minutes, 60), $minutes % 60);
	}
}
