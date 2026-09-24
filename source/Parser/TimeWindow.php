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

	public function __toString(): string {
		return $this->getFrom() . '-' . $this->getTo();
	}

	private static function format(int $minutes): string {
		return sprintf('%02d%02d', intdiv($minutes, 60), $minutes % 60);
	}
}
