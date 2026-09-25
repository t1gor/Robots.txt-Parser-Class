<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser;

/**
 * "Request-rate: 1/5m 0600-0845" - at most one document every five minutes, and only between
 * 06:00 and 08:45 UTC. The time unit defaults to seconds when the letter is left off.
 *
 * @link http://www.conman.org/people/spc/robots2.html
 */
final class RequestRate implements \Stringable {

	private const FORMAT = '/^(\d+)\s*\/\s*(\d+)\s*([smhd])?(?:\s+(\S+))?$/i';

	private const UNITS = ['s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400];

	private function __construct(
		private readonly int $documents,
		private readonly int $seconds,
		private readonly ?TimeWindow $window
	) {
	}

	public static function tryParse(string $value): ?self {
		if (1 !== preg_match(self::FORMAT, trim($value), $parts)) {
			return null;
		}

		$documents = (int) $parts[1];
		$seconds   = (int) $parts[2] * self::UNITS[strtolower(($parts[3] ?? '') ?: 's')];
		$window    = isset($parts[4]) ? TimeWindow::tryParse($parts[4]) : null;

		// neither half of a rate can be zero, and a window given but unreadable is not this rate
		if (0 === $documents || 0 === $seconds || (isset($parts[4]) && is_null($window))) {
			return null;
		}

		return new self($documents, $seconds, $window);
	}

	public function getDocuments(): int {
		return $this->documents;
	}

	/** The period the document count is spread over, whatever unit it was written in. */
	public function getSeconds(): int {
		return $this->seconds;
	}

	/**
	 * The same period as something to do date arithmetic with. Hours rather than days: added to a
	 * zoned date, P1D lands on the same clock time and so moves by 23 or 25 hours over a DST
	 * change, while PT24H is always the 86400 seconds the rate actually means.
	 */
	public function getPeriod(): \DateInterval {
		return new \DateInterval(sprintf(
			'PT%dH%dM%dS',
			intdiv($this->seconds, 3600),
			intdiv($this->seconds % 3600, 60),
			$this->seconds % 60
		));
	}

	public function getWindow(): ?TimeWindow {
		return $this->window;
	}

	/** What a crawler actually needs: how long to wait between two requests. */
	public function getSecondsPerRequest(): float {
		return $this->seconds / $this->documents;
	}

	/** Whether the rate is in force - true whenever it carries no window. */
	public function appliesAt(\DateTimeInterface $moment): bool {
		return is_null($this->window) || $this->window->covers($moment);
	}

	/** The largest unit that divides the period exactly, so "1/300" comes back as "1/5m". */
	public function __toString(): string {
		$unit    = '';
		$seconds = $this->seconds;

		foreach (['d' => 86400, 'h' => 3600, 'm' => 60] as $letter => $size) {
			if (0 === $seconds % $size) {
				$unit    = $letter;
				$seconds = intdiv($seconds, $size);
				break;
			}
		}

		return $this->documents . '/' . $seconds . $unit . (is_null($this->window) ? '' : ' ' . $this->window);
	}
}
