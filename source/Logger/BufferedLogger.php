<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Holds on to messages until a real logger turns up, then hands them over and forwards everything
 * after that.
 *
 * Plenty happens before anyone can call setLogger(): the input is bounded while the parser is being
 * constructed, and a Configuration is usually built earlier still, in a framework's service
 * container. Forwarding rather than merely replaying matters too - stream filters are handed a
 * logger when they are applied and never see a later one, so they keep writing here forever.
 */
final class BufferedLogger extends AbstractLogger {

	/** Enough to explain a parse, capped so an unattached logger cannot grow without end. */
	public const MAX_RECORDS = 500;

	private ?LoggerInterface $target = null;

	/** @var array<int, array{level: mixed, message: mixed, context: array<string, mixed>}> */
	private array $records = [];

	public function log($level, $message, array $context = []): void {
		if (!is_null($this->target)) {
			$this->target->log($level, $message, $context);

			return;
		}

		$this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];

		// oldest first: the tail of a parse explains more than its opening
		if (count($this->records) > self::MAX_RECORDS) {
			array_shift($this->records);
		}
	}

	/**
	 * Hands everything buffered to the real logger and forwards from then on.
	 */
	public function attach(LoggerInterface $target): void {
		// attaching to ourselves would be an endless loop with nowhere to put anything
		if ($target === $this) {
			return;
		}

		$this->target = $target;

		foreach ($this->records as $record) {
			$target->log($record['level'], $record['message'], $record['context']);
		}

		$this->records = [];
	}

	/** @return array<int, array{level: mixed, message: mixed, context: array<string, mixed>}> */
	public function getRecords(): array {
		return $this->records;
	}
}
